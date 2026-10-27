<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Core;

use Estables\Auth\Auth;
use Estables\Db\SchemaException;
use Estables\Db\SchemaState;
use Estables\Http\AuditController;
use Estables\Http\AuthController;
use Estables\Http\CatalogController;
use Estables\Http\ItemsController;
use Estables\Http\MediaController;
use Estables\Http\RubricsController;
use Estables\Http\SetupController;
use Estables\Http\SitemapController;
use Estables\Http\UsersController;
use Estables\Install\Environment;
use Estables\Security\Csp;

/**
 * Handles one web request: configuration, schema self-update, first-run
 * installer, login state, routing, error pages and security headers.
 */
final class Kernel
{
    private const DEFAULT_SOURCE_URL = 'https://github.com/s0me0ne-25/estables';

    private readonly Logger $logger;
    private bool $debug = false;
    private int $hstsMaxAge = 0;

    /** Routes of the current request, so error pages link like the rest of the site. */
    private ?Router $router = null;

    /** Login state of the current request, once the site is installed. */
    private ?Auth $auth = null;

    /** Addresses guests can open while the site is closed for maintenance. */
    private const OPEN_DURING_MAINTENANCE = ['/login/', '/logout/', '/robots.txt'];

    /** @var array<string, mixed> variables shared by every template, error pages included */
    private array $globals = ['sourceUrl' => self::DEFAULT_SOURCE_URL];

    public function __construct(private readonly Paths $paths)
    {
        $this->logger = Services::logger($paths);
    }

    public function run(): void
    {
        new ErrorHandler($this->logger)->register();
        $request = Request::fromGlobals();
        $response = $this->handle($request);
        if ($request->method === 'HEAD') {
            // Same headers and cookies (a session may have been rotated), no body
            $response->clearBody();
        }
        $response->send();
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->process($request);
        } catch (HttpException $e) {
            $response = $this->httpError($e->status);
            foreach ($e->headers as $name => $value) {
                $response->setHeader($name, $value);
            }
        } catch (\Throwable $e) {
            $this->logger->error("Unhandled exception on {$request->method} {$request->path}", $e);
            $response = $this->page('errors/http', [
                'title' => 'Ошибка на сервере',
                'heading' => 'Ошибка на сервере',
                'text' => 'Что-то пошло не так. Подробности записаны в журнал сайта var/log/estables.log.',
                'details' => $this->debug ? $e::class . ': ' . $e->getMessage() . "\n\n" . $e->getTraceAsString() : null,
                'noindex' => true,
            ], 500);
        }

        // Cookie changes (sign-in, rotation, sign-out) reach the browser
        // whatever page the request ended with.
        $this->auth?->apply($response);

        return $this->withSecurityHeaders($response, $request);
    }

    private function process(Request $request): Response
    {
        $this->auth = null;
        $this->router = null;

        // 1. Configuration
        $config = Config::load($this->paths->configFile());
        if ($config === null) {
            return $this->service('setup/config-missing', 'Установка', ['root' => $this->paths->root]);
        }
        $this->debug = $config->bool('app.debug');
        $this->globals['sourceUrl'] = $config->string('app.source_url') ?: self::DEFAULT_SOURCE_URL;
        if ($problems = $config->problems()) {
            return $this->service('setup/problems', 'Ошибка конфигурации', [
                'heading' => 'Ошибка в конфигурации',
                'text' => 'В файле config/config.php есть ошибки:',
                'problems' => $problems,
            ]);
        }
        date_default_timezone_set($config->string('app.timezone'));
        $this->hstsMaxAge = $config->int('security.hsts_max_age');
        $this->globals['siteUrl'] = self::siteUrl($config, $request);
        $services = new Services($this->paths, $config, $this->logger);

        // 2. Database schema: cached check, self-upgrade when the code is newer
        try {
            $schema = $services->schema();
            if (!$schema->isCachedCurrent()) {
                if ($schema->databaseVersion() === 0) {
                    $checks = new Environment($this->paths, $config, $services->db())->checks();
                    if (Environment::errors($checks) !== []) {
                        return $this->service('setup/problems', 'Проверка сервера', [
                            'heading' => 'Сервер не готов к установке',
                            'text' => 'Перед созданием базы нужно исправить ошибки:',
                            'checks' => $checks,
                        ]);
                    }
                }
                $state = $schema->ensureCurrent();
                if ($state === SchemaState::Busy) {
                    return $this->service('errors/http', 'Идёт обновление', [
                        'heading' => 'Идёт обновление',
                        'text' => 'Сайт обновляется и через минуту снова заработает. Обновите страницу чуть позже.',
                        'homeLink' => false,
                    ])->setHeader('Retry-After', '30');
                }
                if ($state === SchemaState::DatabaseNewer) {
                    return $this->service('errors/http', 'Версии не совпадают', [
                        'heading' => 'База данных новее кода',
                        'text' => 'Схема базы обновлена более новой версией eStables, чем установлена сейчас. '
                            . 'Верните на сервер актуальную версию файлов сайта.',
                        'homeLink' => false,
                    ], 500);
                }
            }
        } catch (\PDOException $e) {
            $this->logger->error('Database is unavailable', $e);

            return $this->service('errors/http', 'Нет связи с базой данных', [
                'heading' => 'Нет связи с базой данных',
                'text' => 'Сайт временно недоступен. Проверьте, что MariaDB запущена, а данные подключения в config/config.php верны.',
                'details' => $this->debug ? $e->getMessage() : null,
                'homeLink' => false,
            ]);
        } catch (SchemaException $e) {
            $this->logger->error('Schema upgrade refused', $e);

            return $this->service('errors/http', 'Ошибка обновления базы', [
                'heading' => 'Не удалось обновить базу данных',
                'text' => $e->getMessage(),
                'details' => $this->debug && $e->getPrevious() !== null ? $e->getPrevious()->getMessage() : null,
                'homeLink' => false,
            ]);
        }

        // 3. First run: until an administrator exists, everything leads to the installer
        $installed = $services->installState()->isInstalled();
        if (!$installed && $request->path !== '/setup/') {
            return Response::redirect('/setup/');
        }
        if ($installed && $request->path === '/setup/') {
            throw HttpException::notFound();
        }

        // 4. Login state; the admin area is for signed-in administrators only
        $auth = null;
        if ($installed) {
            $this->auth = $auth = new Auth($services->sessions(), $request);
            $session = $auth->session();
            $this->globals['authState'] = $session === null ? 'guest' : 'admin';
            $this->globals['currentUser'] = $session?->user;
            $this->globals['csrfToken'] = $session === null ? '' : $services->csrf()->token($session);
            $this->globals['media'] = $services->media();
            // Administrators browse a closed site with a reminder on every page
            $this->globals['maintenance'] = $session !== null && $services->settings()->maintenance();

            if ($request->path === '/admin' || str_starts_with($request->path, '/admin/')) {
                if ($session === null) {
                    $query = $request->query === [] ? '' : '?' . http_build_query($request->query);

                    return Response::redirect('/login/?next=' . rawurlencode($request->path . $query));
                }
                if ($request->isPost() && !$services->csrf()->check($request, $session)) {
                    throw new HttpException(403, 'CSRF');
                }
                $services->audit()->actAs($session->user, $request->ip());
            }
        }

        // 5. Routing
        $this->router = $router = new Router();
        $view = new View($this->paths->templates(), $this->paths->public(), $router, $this->globals);
        $catalog = new CatalogController($view, $services, $auth?->session() !== null);
        $router->add('home', 'GET', '/', $catalog->home(...));
        $router->add('about', 'GET', '/about/', $catalog->about(...));
        $router->add('catalog', 'GET', '/catalog/', $catalog->catalogRoot(...));
        $router->add('rubric', 'GET', '/catalog/{path:.+}/', $catalog->rubric(...));
        $router->add('item', 'GET', '/item/{slug}/', $catalog->item(...));
        $router->add('search', 'GET', '/search/', $catalog->search(...));
        $media = new MediaController($services);
        $router->add('media.copy', 'GET', '/media/{size:\d+}/{dir:[0-9a-f][0-9a-f]}/{sha:[0-9a-f]+}.{ext:jpg|png|gif}', $media->copy(...));
        $router->add('media.download', 'GET', '/download/{sha:[0-9a-f]+}/{name}', $media->download(...));
        $sitemap = new SitemapController($services, (string) $this->globals['siteUrl']);
        $router->add('robots', 'GET', '/robots.txt', $sitemap->robots(...));
        $router->add('sitemap', 'GET', '/sitemap.xml', $sitemap->index(...));
        $router->add('sitemap.pages', 'GET', '/sitemap-pages.xml', $sitemap->pages(...));
        $router->add('sitemap.items', 'GET', '/sitemap-items-{n:\d+}.xml', $sitemap->items(...));
        if ($auth === null) {
            $setup = new SetupController($services, $view, $this->paths);
            $router->add('setup', ['GET', 'POST'], '/setup/', $setup->handle(...));
        } else {
            $login = new AuthController($services, $view, $auth);
            $items = new ItemsController($services, $view, $auth);
            $users = new UsersController($services, $view, $auth);
            $router->add('login', ['GET', 'POST'], '/login/', $login->login(...));
            $router->add('logout', 'POST', '/logout/', $login->logout(...));
            $router->add('admin', 'GET', '/admin/', $items->index(...));
            $router->add('admin.items.bulk', 'POST', '/admin/items/bulk/', $items->bulk(...));
            $router->add('admin.items.order', 'POST', '/admin/items/order/', $items->order(...));
            $router->add('admin.items.new', ['GET', 'POST'], '/admin/items/new/', $items->edit(...));
            $router->add('admin.items.edit', ['GET', 'POST'], '/admin/items/{id:\d+}/', $items->edit(...));
            $router->add('admin.items.photos', 'POST', '/admin/items/{id:\d+}/photos/', $items->upload(...));
            $rubrics = new RubricsController($services, $view);
            $router->add('admin.rubrics', ['GET', 'POST'], '/admin/rubrics/', $rubrics->handle(...));
            $router->add('admin.rubrics.move', 'POST', '/admin/rubrics/move/', $rubrics->move(...));
            $router->add('admin.users', ['GET', 'POST'], '/admin/users/', $users->handle(...));
            $router->add('admin.audit', 'GET', '/admin/audit/', new AuditController($services, $view)->index(...));
        }

        // Maintenance mode: guests get the stub everywhere except the sign-in
        if ($auth !== null && $auth->session() === null
            && !\in_array($request->path, self::OPEN_DURING_MAINTENANCE, true)
            && $services->settings()->maintenance()) {
            return Response::html($view->page('pages/maintenance', [
                'title' => 'Технические работы',
                'noindex' => true,
                'activeNav' => '',
            ]), 503)
                ->setHeader('Retry-After', '3600')
                ->setHeader('Cache-Control', 'no-store');
        }

        return $router->dispatch($request);
    }

    /**
     * Service page with the reduced header, never cached or indexed.
     *
     * @param array<string, mixed> $vars
     */
    private function service(string $template, string $title, array $vars, int $status = 503): Response
    {
        return $this->page($template, ['title' => $title, 'header' => 'minimal', 'noindex' => true] + $vars, $status)
            ->setHeader('Cache-Control', 'no-store');
    }

    private function httpError(int $status): Response
    {
        [$heading, $text] = match ($status) {
            403 => ['Действие отклонено', 'Форма устарела или отправлена с другого сайта. Вернитесь назад, обновите страницу и повторите действие.'],
            404 => ['Страница не найдена', 'Возможно, адрес набран с ошибкой или страница была перемещена.'],
            405 => ['Действие недоступно', 'Эта страница не поддерживает такой запрос.'],
            default => ['Ошибка ' . $status, 'Запрос не может быть выполнен.'],
        };

        return $this->page('errors/http', [
            'title' => $heading,
            'heading' => $heading,
            'text' => $text,
            'noindex' => true,
        ], $status);
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function page(string $template, array $vars, int $status): Response
    {
        $view = new View($this->paths->templates(), $this->paths->public(), $this->router, $this->globals);

        return Response::html($view->page($template, $vars), $status);
    }

    /**
     * Headers every response gets unless the page set its own.
     */
    private function withSecurityHeaders(Response $response, Request $request): Response
    {
        $response
            ->defaultHeader('X-Content-Type-Options', 'nosniff')
            ->defaultHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->defaultHeader('X-Frame-Options', 'DENY')
            ->defaultHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), browsing-topics=()')
            ->defaultHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->defaultHeader('Cross-Origin-Resource-Policy', 'same-origin')
            ->defaultHeader('Content-Security-Policy', Csp::header());
        if ($this->hstsMaxAge > 0 && $request->isHttps()) {
            // Plain HTTP stays possible for the first visit only; nginx redirects it.
            $response->defaultHeader('Strict-Transport-Security', 'max-age=' . $this->hstsMaxAge);
        }

        return $response;
    }

    /**
     * Absolute address of the site for canonical links, Open Graph and the
     * sitemap: app.base_url, or the address of this request when it is empty.
     */
    private static function siteUrl(Config $config, Request $request): string
    {
        $base = $config->string('app.base_url');
        if ($base !== '') {
            return $base;
        }
        $host = $request->server['HTTP_HOST'] ?? '';
        $host = \is_string($host) && preg_match('~^[a-z0-9.-]+(:\d+)?$~i', $host) ? strtolower($host) : 'localhost';

        return ($request->isHttps() ? 'https://' : 'http://') . $host;
    }
}

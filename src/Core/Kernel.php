<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Core;

use Estables\Db\SchemaException;
use Estables\Db\SchemaState;
use Estables\Http\CatalogController;
use Estables\Http\SetupController;
use Estables\Install\Environment;

/**
 * Handles one web request: configuration, schema self-update, first-run
 * installer, routing, error pages and security headers.
 */
final class Kernel
{
    private const DEFAULT_SOURCE_URL = 'https://github.com/s0me0ne-25/estables';

    private readonly Logger $logger;
    private bool $debug = false;
    private string $sourceUrl = self::DEFAULT_SOURCE_URL;

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
            $response = new Response('', $response->status(), array_filter([
                'Content-Type' => $response->header('Content-Type') ?? '',
                'Location' => $response->header('Location') ?? '',
            ]));
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

        return self::withSecurityHeaders($response);
    }

    private function process(Request $request): Response
    {
        // 1. Configuration
        $config = Config::load($this->paths->configFile());
        if ($config === null) {
            return $this->service('setup/config-missing', 'Установка', ['root' => $this->paths->root]);
        }
        $this->debug = $config->bool('app.debug');
        $this->sourceUrl = $config->string('app.source_url') ?: self::DEFAULT_SOURCE_URL;
        if ($problems = $config->problems()) {
            return $this->service('setup/problems', 'Ошибка конфигурации', [
                'heading' => 'Ошибка в конфигурации',
                'text' => 'В файле config/config.php есть ошибки:',
                'problems' => $problems,
            ]);
        }
        date_default_timezone_set($config->string('app.timezone'));
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

        // 3. Routing
        $router = new Router();
        $view = new View($this->paths->templates(), $this->paths->public(), $router, [
            'sourceUrl' => $this->sourceUrl,
        ]);
        $setup = new SetupController($services, $view, $this->paths);
        $catalog = new CatalogController($view);

        $router->add('home', 'GET', '/', $catalog->home(...));
        $router->add('setup', ['GET', 'POST'], '/setup/', $setup->handle(...));

        // 4. First run: until an administrator exists, everything leads to the installer
        $installed = $services->installState()->isInstalled();
        if (!$installed && $request->path !== '/setup/') {
            return Response::redirect('/setup/');
        }
        if ($installed && $request->path === '/setup/') {
            throw HttpException::notFound();
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
        $view = new View($this->paths->templates(), $this->paths->public(), null, [
            'sourceUrl' => $this->sourceUrl,
        ]);

        return Response::html($view->page($template, $vars), $status);
    }

    private static function withSecurityHeaders(Response $response): Response
    {
        return $response
            ->defaultHeader('X-Content-Type-Options', 'nosniff')
            ->defaultHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->defaultHeader('X-Frame-Options', 'DENY')
            ->defaultHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->defaultHeader(
                'Content-Security-Policy',
                "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; font-src 'self'; "
                . "connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'",
            );
    }
}

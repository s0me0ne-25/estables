<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Cli;

use Estables\Auth\UserRepository;
use Estables\Core\Config;
use Estables\Core\Paths;
use Estables\Core\Services;
use Estables\Db\Schema;
use Estables\Db\SchemaException;
use Estables\Db\SchemaState;
use Estables\Install\Check;
use Estables\Install\Environment;
use Estables\Media\GarbageCollector;

/**
 * Command line interface: bin/estables <command> [arguments].
 */
final class Application
{
    private const COMMANDS = [
        'check' => 'проверить сервер и подключение к базе',
        'migrate' => 'создать или обновить схему базы данных',
        'status' => 'показать версию схемы базы и кода',
        'create-admin' => 'создать администратора: create-admin [логин] [--name="Имя"] [--password-stdin]',
        'passwd' => 'сменить пароль и завершить все сессии: passwd <логин> [--password-stdin]',
        'unlock' => 'снять блокировку входа после неудачных попыток: unlock <логин>',
        'gc' => 'удалить неиспользуемые фото, истёкшие сессии и старые попытки входа (для cron)',
        'media:warm' => 'заранее сделать уменьшенные копии всех фото',
    ];

    /** @var resource */
    private $out;

    /** @var resource */
    private $err;

    /** @var resource */
    private $in;

    /**
     * @param resource|null $in
     * @param resource|null $out
     * @param resource|null $err
     */
    public function __construct(private readonly Paths $paths, $in = null, $out = null, $err = null)
    {
        $this->in = $in ?? STDIN;
        $this->out = $out ?? STDOUT;
        $this->err = $err ?? STDERR;
    }

    /**
     * @param list<string> $argv arguments without the script name
     *
     * @return int exit code
     */
    public function run(array $argv): int
    {
        $command = $argv[0] ?? 'help';
        $args = \array_slice($argv, 1);

        try {
            return match ($command) {
                'check' => $this->check(),
                'migrate' => $this->migrate(),
                'status' => $this->status(),
                'create-admin' => $this->createAdmin($args),
                'passwd' => $this->passwd($args),
                'unlock' => $this->unlock($args),
                'gc' => $this->gc(),
                'media:warm' => $this->warm(),
                'help', '--help', '-h' => $this->help(),
                default => $this->fail("Неизвестная команда «{$command}». Список: bin/estables help"),
            };
        } catch (CliException $e) {
            return $this->fail($e->getMessage());
        } catch (\PDOException $e) {
            return $this->fail('Нет связи с базой данных: ' . $e->getMessage());
        } catch (SchemaException $e) {
            $previous = $e->getPrevious() !== null ? "\n" . $e->getPrevious()->getMessage() : '';

            return $this->fail($e->getMessage() . $previous);
        }
    }

    private function help(): int
    {
        $this->line('Использование: bin/estables <команда>');
        $this->line('');
        foreach (self::COMMANDS as $name => $description) {
            $this->line(\sprintf('  %-14s %s', $name, $description));
        }

        return 0;
    }

    private function check(): int
    {
        $config = $this->config(false);
        $services = $config !== null ? new Services($this->paths, $config, Services::logger($this->paths)) : null;
        if ($config === null) {
            $this->line('Конфигурация: нет config/config.php — подключение к базе не проверяется.');
        } else {
            foreach ($config->problems() as $problem) {
                $this->line(\sprintf('%-10s', 'ОШИБКА') . $problem);
            }
        }
        $checks = new Environment($this->paths, $config, $services?->db())->checks();
        $labels = [Check::OK => 'ok', Check::WARNING => 'внимание', Check::ERROR => 'ОШИБКА'];
        foreach ($checks as $check) {
            $this->line(\sprintf('%-10s', $labels[$check->status]) . $check->title . ($check->ok() ? '' : ' — ' . $check->hint));
        }

        return Environment::errors($checks) === [] && ($config === null || $config->problems() === []) ? 0 : 1;
    }

    private function migrate(): int
    {
        $services = $this->services();
        $schema = $services->schema();
        $from = $schema->databaseVersion();
        if ($from === 0) {
            $errors = Environment::errors(new Environment($this->paths, $services->config, $services->db())->checks());
            if ($errors !== []) {
                foreach ($errors as $check) {
                    $this->error($check->title . ' — ' . $check->hint);
                }

                return $this->fail('Сервер не готов к установке, см. bin/estables check.');
            }
        }
        $schema->invalidateCache();
        $state = $schema->ensureCurrent(60);

        return match ($state) {
            SchemaState::Current => $from === Schema::VERSION
                ? $this->ok('Схема уже актуальна: версия ' . Schema::VERSION . '.')
                : $this->ok("Схема обновлена: версия {$from} → " . Schema::VERSION . '.'),
            SchemaState::Busy => $this->fail('Схему сейчас обновляет другой процесс; повторите позже.'),
            SchemaState::DatabaseNewer => $this->fail('База новее кода (версия ' . $schema->databaseVersion()
                . ', код знает ' . Schema::VERSION . '). Обновите файлы сайта.'),
        };
    }

    private function status(): int
    {
        $schema = $this->services()->schema();
        $this->line('Версия схемы в базе: ' . $schema->databaseVersion());
        $this->line('Версия схемы в коде: ' . Schema::VERSION);

        return 0;
    }

    /**
     * @param list<string> $args
     */
    private function createAdmin(array $args): int
    {
        $services = $this->currentServices();

        $login = '';
        $name = '';
        $passwordFromStdin = false;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--name=')) {
                $name = trim(substr($arg, 7));
            } elseif ($arg === '--password-stdin') {
                $passwordFromStdin = true;
            } elseif (!str_starts_with($arg, '--') && $login === '') {
                $login = $arg;
            } else {
                return $this->fail("Непонятный аргумент «{$arg}».");
            }
        }
        if ($login === '') {
            $login = $this->ask('Логин: ');
        }

        if ($passwordFromStdin) {
            $password = rtrim((string) fgets($this->in), "\r\n");
            $again = $password;
        } else {
            $password = $this->askHidden('Пароль: ');
            $again = $this->askHidden('Пароль ещё раз: ');
        }

        $users = $services->users();
        $errors = $users->validateNew($login, $password, $again, $name);
        if ($errors !== []) {
            foreach ($errors as $field => $message) {
                $this->error("{$field}: {$message}");
            }

            return 1;
        }
        $users->create($login, $password, $name);
        $services->installState()->markInstalled();
        $services->logger->info("Administrator \"{$login}\" created from the command line");

        return $this->ok("Администратор «{$login}» создан.");
    }

    /**
     * @param list<string> $args
     */
    private function passwd(array $args): int
    {
        $passwordFromStdin = \in_array('--password-stdin', $args, true);
        $logins = array_values(array_filter($args, static fn(string $arg): bool => !str_starts_with($arg, '--')));
        if (\count($logins) !== 1 || \count($logins) + (int) $passwordFromStdin !== \count($args)) {
            return $this->fail('Использование: bin/estables passwd <логин> [--password-stdin]');
        }
        $services = $this->currentServices();
        $user = $services->users()->findByLogin($logins[0]);
        if ($user === null) {
            return $this->fail("Администратора «{$logins[0]}» нет.");
        }

        if ($passwordFromStdin) {
            $password = rtrim((string) fgets($this->in), "\r\n");
            $again = $password;
        } else {
            $password = $this->askHidden('Новый пароль: ');
            $again = $this->askHidden('Новый пароль ещё раз: ');
        }
        $errors = UserRepository::validatePassword($password, $again);
        if ($errors !== []) {
            foreach ($errors as $message) {
                $this->error($message);
            }

            return 1;
        }
        $services->users()->setPassword($user->id, $password);
        $services->sessions()->deleteForUser($user->id);
        $services->logger->info("Password of \"{$user->login}\" changed from the command line, sessions ended");

        return $this->ok("Пароль «{$user->login}» изменён, все его сессии завершены."
            . ($user->active ? '' : ' Учётная запись заблокирована: разблокируйте её в панели управления.'));
    }

    /**
     * @param list<string> $args
     */
    private function unlock(array $args): int
    {
        if (\count($args) !== 1 || str_starts_with($args[0], '--')) {
            return $this->fail('Использование: bin/estables unlock <логин>');
        }
        $count = $this->currentServices()->throttle()->clear($args[0]);

        return $this->ok("Неудачных попыток входа «{$args[0]}» сброшено: {$count}. Блокировка по IP-адресу снимается сама через login.lockout_minutes.");
    }

    private function gc(): int
    {
        $services = $this->currentServices();
        $media = new GarbageCollector($services->images(), $services->media(), $services->config->int('media.gc_delay_hours'))->run();
        $sessions = $services->sessions()->purgeExpired();
        $attempts = $services->throttle()->purgeOld();
        $services->logger->info("gc: {$media['images']} images, {$media['files']} files, {$sessions} sessions, {$attempts} login attempts");

        return $this->ok("Удалено: изображений {$media['images']}, файлов без записи в базе {$media['files']}, "
            . "истёкших сессий {$sessions}, старых попыток входа {$attempts}.");
    }

    private function warm(): int
    {
        $services = $this->currentServices();
        $media = $services->media();
        $thumbnailer = $services->thumbnailer();
        $made = 0;
        $failed = 0;
        foreach ($services->images()->all() as $image) {
            foreach ([$media->thumbSize(), $media->largeSize()] as $size) {
                $needed = $size === $media->thumbSize()
                    ? $media->thumbUrl($image) !== $media->originalUrl($image)
                    : $media->largeUrl($image) !== $media->originalUrl($image);
                if (!$needed || is_file($media->copyPath($size, $image->sha, $image->ext))) {
                    continue;
                }
                try {
                    $thumbnailer->copy($image, $size);
                    $made++;
                } catch (\Throwable $e) {
                    $this->error("{$image->sha}: " . $e->getMessage());
                    $failed++;
                }
            }
        }

        return $failed === 0 ? $this->ok("Сделано копий: {$made}.") : $this->fail("Сделано копий: {$made}, ошибок: {$failed}.");
    }

    /**
     * Services of an installation whose schema is up to date.
     */
    private function currentServices(): Services
    {
        $services = $this->services();
        $schema = $services->schema();
        if (!$schema->isCachedCurrent() && $schema->databaseVersion() !== Schema::VERSION) {
            throw new CliException('Схема базы не актуальна: выполните bin/estables migrate');
        }

        return $services;
    }

    private function config(bool $required): ?Config
    {
        $config = Config::load($this->paths->configFile());
        if ($config === null && $required) {
            throw new CliException('Нет config/config.php: скопируйте config/config.template.php и заполните его.');
        }
        if ($config !== null) {
            date_default_timezone_set($config->string('app.timezone') ?: 'UTC');
        }

        return $config;
    }

    private function services(): Services
    {
        $config = $this->config(true);
        \assert($config !== null);
        if ($problems = $config->problems()) {
            throw new CliException("Ошибки в config/config.php:\n" . implode("\n", $problems));
        }

        return new Services($this->paths, $config, Services::logger($this->paths));
    }

    private function ask(string $prompt): string
    {
        fwrite($this->out, $prompt);

        return trim((string) fgets($this->in));
    }

    private function askHidden(string $prompt): string
    {
        fwrite($this->out, $prompt);
        $tty = \function_exists('posix_isatty') && posix_isatty($this->in);
        if ($tty) {
            shell_exec('stty -echo');
        }
        try {
            $value = rtrim((string) fgets($this->in), "\r\n");
        } finally {
            if ($tty) {
                shell_exec('stty echo');
                fwrite($this->out, "\n");
            }
        }

        return $value;
    }

    private function line(string $text): void
    {
        fwrite($this->out, $text . "\n");
    }

    private function error(string $text): void
    {
        fwrite($this->err, $text . "\n");
    }

    private function ok(string $text): int
    {
        $this->line($text);

        return 0;
    }

    private function fail(string $text): int
    {
        $this->error($text);

        return 1;
    }
}

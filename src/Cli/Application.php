<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Cli;

use Estables\Core\Config;
use Estables\Core\Paths;
use Estables\Core\Services;
use Estables\Db\Schema;
use Estables\Db\SchemaException;
use Estables\Db\SchemaState;
use Estables\Install\Check;
use Estables\Install\Environment;

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
        $services = $this->services();
        if (!$services->schema()->isCachedCurrent() && $services->schema()->databaseVersion() !== Schema::VERSION) {
            return $this->fail('Сначала создайте схему базы: bin/estables migrate');
        }

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

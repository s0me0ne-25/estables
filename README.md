# eStables

Справочник для коллекционеров: каталог с иерархическим рубрикатором и фотогалереями. PHP 8.4 и MariaDB 11.8, без фреймворка.

- [docs/INSTALL.md](docs/INSTALL.md) — установка на VPS: Debian 13, nginx, php-fpm, MariaDB, HTTPS от Let's Encrypt.
- [docs/UPGRADE.md](docs/UPGRADE.md) — обновление и откат.

## Демонстрационная версия

[https://s0me0ne-25.github.io/estables/](https://s0me0ne-25.github.io/estables/) - статическая демо-версия CMS, позволяет оценить дизайн и функциональность без развёртывания на хостинг. Содержит страницы с условным контентом, сгенерированные реальным движком eStables, и локальной имитацией некоторых функций сервера средствами JavaScript.

## Разработка

- [docs/SANDBOX.md](docs/SANDBOX.md) — LNMP-песочница для проверки сборок.
- [docs/BUILDS.md](docs/BUILDS.md) — что умеет каждая сборка и как это проверить.
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — архитектура и план разработки.
- [demo/](demo/) — статическая демо-версия: страницы, отрендеренные движком с условным контентом; публикуется на GitHub Pages. Как её обновлять — [tools/demo-build/](tools/demo-build/README.md).

```sh
composer install
composer cs        # стиль кода (PER-CS)
composer stan      # статический анализ (PHPStan, уровень 8)
composer test      # тесты; интеграционным нужна пустая база MariaDB:
ESTABLES_TEST_DB_NAME=estables_test ESTABLES_TEST_DB_USER=… ESTABLES_TEST_DB_PASSWORD=… composer test
```

Лицензия: [GNU AGPL v3](LICENSE).

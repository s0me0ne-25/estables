# eStables

Справочник для коллекционеров: каталог с иерархическим рубрикатором и фотогалереями. PHP 8.4 и MariaDB 11.8, без фреймворка.

- [docs/INSTALL.md](docs/INSTALL.md) — установка и обновление (Debian 13, nginx, php-fpm, MariaDB).
- [docs/BUILDS.md](docs/BUILDS.md) — что умеет каждая сборка и как это проверить.
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — архитектура и план разработки.
- [demo/](demo/) — статическое демо, эталон оформления; публикуется на GitHub Pages.

## Разработка

```sh
composer install
composer cs        # стиль кода (PER-CS)
composer stan      # статический анализ (PHPStan, уровень 8)
composer test      # тесты; интеграционным нужна пустая база MariaDB:
ESTABLES_TEST_DB_NAME=estables_test ESTABLES_TEST_DB_USER=… ESTABLES_TEST_DB_PASSWORD=… composer test
```

Лицензия: [GNU AGPL v3](LICENSE).

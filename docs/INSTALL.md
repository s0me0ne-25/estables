# Установка eStables

Инструкция для Debian 13 (trixie) с nginx, php8.4-fpm и MariaDB 11.8. Упрощённый вариант для песочницы, в которой проверяются сборки, — [SANDBOX.md](SANDBOX.md).

Пути ниже предполагают каталог `/var/www/estables` и пользователя php-fpm `www-data`.

## 1. Пакеты

```sh
apt install nginx php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-intl php8.4-imagick \
    mariadb-server mariadb-client libjpeg-turbo-progs composer git
```

`php8.4-imagick` и `libjpeg-turbo-progs` понадобятся для фотографий (сборка 5); до этого проверка сервера только предупреждает об их отсутствии.

## 2. База данных

```sh
mariadb <<'SQL'
CREATE DATABASE estables CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci;
CREATE USER 'estables'@'localhost' IDENTIFIED BY 'сюда-длинный-пароль';
GRANT ALL PRIVILEGES ON estables.* TO 'estables'@'localhost';
SQL
```

База должна быть пустой: в непустую базу без таблиц eStables движок устанавливаться откажется.

## 3. Код

```sh
git clone https://github.com/s0me0ne-25/estables.git /var/www/estables
cd /var/www/estables
composer install --no-dev --optimize-autoloader

# Каталоги, в которые пишет сайт
mkdir -p var public/media
chown -R www-data:www-data var public/media
```

## 4. Конфигурация

```sh
cp config/config.template.php config/config.php
chown root:www-data config/config.php
chmod 640 config/config.php
```

В `config/config.php` заполните:

- `app.base_url` — адрес сайта, например `https://estables.example.org`;
- `app.secret` — случайная строка: `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`;
- `db.socket` — `/run/mysqld/mysqld.sock` (или `db.host`/`db.port`), `db.name`, `db.user`, `db.password`.

Файл `config/config.php` в git не попадает.

## 5. php-fpm и nginx

```sh
cp deploy/php-fpm/estables.conf /etc/php/8.4/fpm/pool.d/estables.conf
systemctl reload php8.4-fpm

cp deploy/nginx/estables.conf /etc/nginx/sites-available/estables
# поправьте server_name и root
ln -s ../sites-available/estables /etc/nginx/sites-enabled/estables
nginx -t && systemctl reload nginx
```

Если на сервере нет IPv6, уберите из конфигурации nginx строку `listen [::]:80;`. HTTPS: `certbot --nginx -d <домен>`.

## 6. Первый запуск

Схема базы создаётся сама при первом открытии сайта. Дальше есть два способа создать первого администратора.

**Через браузер.** Сайт покажет форму «Первый администратор». Она защищена одноразовым токеном, который лежит только на сервере:

```sh
cat /var/www/estables/var/install.token
```

После создания администратора токен удаляется, а форма становится недоступной.

**Из консоли** (команды запускаются от `www-data`, чтобы файлы в `var/` принадлежали ему):

```sh
sudo -u www-data php bin/estables migrate
sudo -u www-data php bin/estables create-admin
```

Проверить сервер можно командой `sudo -u www-data php bin/estables check`.

Войти в панель управления: ссылка «Вход администратора» в подвале сайта или адрес `/login/`.

## 7. Вход, сессии и капча

Настройки — в разделах `session`, `login` и `captcha` файла `config/config.php` (пояснения в `config/config.template.php`).

- В cookie сессии лежит только случайный идентификатор; в базе — его SHA-256. На HTTPS cookie называется `__Host-es_session`, по HTTP — `es_session`.
- Без «Запомнить меня» вход живёт до закрытия браузера, а на сервере завершается после 12 часов без активности (`session.idle_hours`). С отметкой — 30 дней (`session.remember_days`), срок продлевается при каждом заходе.
- После 5 неудачных попыток подряд для логина или 20 с одного IP форма входа блокируется на 15 минут (раздел `login`). Каждая неудача пишется в `var/log/estables.log` строкой `Login failed for "<логин>" from <IP>` — по ней можно настроить fail2ban. Снять блокировку логина досрочно: `bin/estables unlock <логин>`.
- Капча: `stub` — простая галочка без настоящей проверки (для разработки или когда сайт закрыт Anubis/fail2ban); `turnstile` — [Cloudflare Turnstile](https://developers.cloudflare.com/turnstile/): создайте виджет в панели Cloudflare и впишите `site_key` и `secret_key`. Серверу нужен исходящий HTTPS к `challenges.cloudflare.com` и `allow_url_fopen = On` (в Debian включено по умолчанию).
- Забытый пароль меняется из консоли: `sudo -u www-data php bin/estables passwd <логин>`; все сессии этого администратора при этом завершаются.

## 8. Обновление до новой сборки

```sh
cd /var/www/estables
git pull                      # или git apply <патч>
composer install --no-dev --optimize-autoloader
sudo -u www-data php bin/estables migrate
```

Перед обновлением схемы делается резервная копия базы в `var/backups/`. Если `migrate` забыть, схема обновится сама при первом запросе к сайту; на эти секунды посетители увидят страницу «Идёт обновление».

## Команды

| Команда | Что делает |
|---|---|
| `bin/estables check` | проверяет PHP, расширения, права на запись и подключение к MariaDB |
| `bin/estables migrate` | создаёт или обновляет схему базы |
| `bin/estables status` | показывает версию схемы в базе и в коде |
| `bin/estables create-admin [логин] [--name="Имя"] [--password-stdin]` | создаёт администратора |
| `bin/estables passwd <логин> [--password-stdin]` | меняет пароль администратора и завершает все его сессии |
| `bin/estables unlock <логин>` | сбрасывает неудачные попытки входа, снимая блокировку логина |

## Журнал и резервные копии

- Журнал: `var/log/estables.log`.
- Резервные копии перед обновлением схемы: `var/backups/estables-<дата>-before-v<версия>.sql.gz`, хранятся последние 10 (`backup.keep`).

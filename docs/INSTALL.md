# Установка eStables

Инструкция для Debian 13 (trixie) с nginx, php8.4-fpm и MariaDB 11.8. Упрощённый вариант для песочницы, в которой проверяются сборки, — [SANDBOX.md](SANDBOX.md).

Пути ниже предполагают каталог `/var/www/estables` и пользователя php-fpm `www-data`.

## 1. Пакеты

```sh
apt install sudo nginx php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-intl php8.4-imagick \
    mariadb-server mariadb-client libjpeg-turbo-progs composer git certbot
```

`sudo` в минимальной установке Debian нет, а консольные команды движка ниже запускаются через `sudo -u www-data`. `certbot` нужен для сертификата HTTPS (п. 5).

`php8.4-imagick` обязателен: без него не обработать фотографии. `libjpeg-turbo-progs` (утилита `jpegtran`) желателен: с ним JPEG очищается от метаданных и поворачивается без пересжатия; без него движок пересжимает только повёрнутые снимки, а проверка сервера об этом предупреждает.

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

- `app.base_url` — адрес сайта, например `https://example.org` (пока нет сертификата — `http://…`);
- `app.secret` — случайная строка: `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`;
- `db.socket` — `/run/mysqld/mysqld.sock` (или `db.host`/`db.port`), `db.name`, `db.user`, `db.password`.

Файл `config/config.php` в git не попадает.

## 5. php-fpm и nginx

```sh
cp deploy/php-fpm/estables.conf /etc/php/8.4/fpm/pool.d/estables.conf
systemctl reload php8.4-fpm

# Общая часть сайта: корень, статика, фото, PHP
cp deploy/nginx/estables-app.conf /etc/nginx/snippets/estables-app.conf

# Сначала сайт по HTTP: на нём certbot подтвердит домен
cp deploy/nginx/estables-http.conf /etc/nginx/sites-available/estables
# в файле замените example.org на свой домен
ln -s ../sites-available/estables /etc/nginx/sites-enabled/estables
mkdir -p /var/www/acme-stub
nginx -t && systemctl reload nginx
```

Если сайт лежит не в `/var/www/estables`, поправьте `root` в `/etc/nginx/snippets/estables-app.conf`. Если у сервера есть IPv6, добавьте в блоки `server` строки `listen [::]:80;` и `listen [::]:443 ssl;`.

**HTTPS.** Когда домен указывает на сервер и сайт открывается по HTTP:

```sh
certbot certonly --webroot -w /var/www/acme-stub -d example.org -d www.example.org

cp deploy/nginx/estables.conf /etc/nginx/sites-available/estables
# снова замените example.org на свой домен (в server_name, в пути к сертификату и в адресе перенаправления)
nginx -t && systemctl reload nginx
```

Certbot конфигурацию nginx не трогает: он кладёт файлы проверки в `/var/www/acme-stub`, а сертификаты — в `/etc/letsencrypt/live/<домен>/`. HTTP после этого отвечает только на проверки Let's Encrypt и перенаправляет всё остальное на `https://<домен>`. Продление — строкой в `/etc/crontab` (п. 9). В `config/config.php` поменяйте `app.base_url` на `https://…`.

По HTTPS движок отправляет заголовок HSTS (`security.hsts_max_age`, по умолчанию год): после первого захода браузер больше не откроет сайт по HTTP. Пока HTTPS временный, поставьте `0`.

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
- После 5 неудачных попыток подряд для логина или 20 с одного IP форма входа блокируется на 15 минут (раздел `login`). Каждая неудача пишется в `var/log/estables.log` строкой `Login failed for "<логин>" from <IP>`; по ней работает fail2ban (п. 9). Снять блокировку логина досрочно: `bin/estables unlock <логин>`.
- Капча: `stub` — простая галочка без настоящей проверки (для разработки или когда сайт закрыт Anubis/fail2ban); `turnstile` — [Cloudflare Turnstile](https://developers.cloudflare.com/turnstile/): создайте виджет в панели Cloudflare и впишите `site_key` и `secret_key`. Серверу нужен исходящий HTTPS к `challenges.cloudflare.com` и `allow_url_fopen = On` (в Debian включено по умолчанию).
- Забытый пароль меняется из консоли: `sudo -u www-data php bin/estables passwd <логин>`; все сессии этого администратора при этом завершаются.

## 8. Фотографии

Настройки — раздел `media` файла `config/config.php` (пояснения в `config/config.template.php`).

- Оригиналы лежат в `public/media/originals/`, очищенные от метаданных (EXIF с геометкой, XMP, комментарии). JPEG, PNG и GIF сохраняются в своём формате, WebP, AVIF, BMP и TIFF преобразуются в JPEG, HEIC не принимается. Файл называется по SHA-256 содержимого, поэтому одинаковые фото хранятся один раз.
- Уменьшенные копии (`thumb_size` 400 и `large_size` 1600 по длинной стороне) делаются при первом запросе и складываются в `public/media/<размер>/`; дальше их отдаёт nginx без PHP. Сделать все копии заранее, например после смены размеров: `sudo -u www-data php bin/estables media:warm`. После смены размеров старые каталоги копий можно удалить.
- «Скачать оригинал» идёт через `/download/…`: движок проверяет файл, а отдаёт его nginx (`X-Accel-Redirect`).
- Предел файла — `max_upload_mb` (50 МБ). Он должен укладываться в `upload_max_filesize` пула php-fpm и `client_max_body_size` nginx: в конфигурациях из `deploy/` это 50 и 64 МБ.
- Удалённые из предметов фото и файлы без записи в базе убирает `bin/estables gc` — не раньше чем через `gc_delay_hours` (24 часа) после загрузки. Его запускает cron (п. 9).

Каталог `public/media/` в git не попадает; в резервную копию сайта его нужно включать вместе с базой (п. 10).

## 9. Обслуживание: cron, журнал, fail2ban

Готовые файлы лежат в `deploy/`; пути в них — `/var/www/estables` и пользователь `www-data`.

**cron.** Все задания — в общем `/etc/crontab`, строки для него лежат в `deploy/crontab`:

```
37  3  *   *   *   www-data  /usr/bin/php /var/www/estables/bin/estables backup > /dev/null
17  4  *   *   *   www-data  /usr/bin/php /var/www/estables/bin/estables gc > /dev/null
0   3  *   *   *   root      /usr/bin/certbot renew -w /var/www/acme-stub --post-hook "/usr/bin/systemctl reload nginx"
```

Допишите их в конец `/etc/crontab` редактором (`nano /etc/crontab`); строку certbot — когда настроен HTTPS (п. 5). cron перечитывает `/etc/crontab` сам. Ошибки уходят в почту cron, обычный вывод отбрасывается. Проверить вручную: `sudo -u www-data php bin/estables backup` и `… gc`.

- `backup` — дамп базы в `var/backups/` (п. 10);
- `gc` удаляет ненужные фото, истёкшие сессии, старые попытки входа и записи «Журнала» старше `audit.keep_days` (два года).

**Ротация журнала** `var/log/estables.log` — раз в неделю, полгода истории:

```sh
cp deploy/logrotate/estables /etc/logrotate.d/estables
logrotate --debug /etc/logrotate.d/estables     # проверка без изменений
```

**fail2ban** блокирует на час адрес, с которого за 10 минут было 10 неудачных входов:

```sh
apt install fail2ban
cp deploy/fail2ban/filter.d/estables.conf /etc/fail2ban/filter.d/
cp deploy/fail2ban/jail.d/estables.conf /etc/fail2ban/jail.d/
fail2ban-client reload
fail2ban-regex /var/www/estables/var/log/estables.log /etc/fail2ban/filter.d/estables.conf
fail2ban-client status estables
```

Время в журнале записано с поясом (`[2026-10-27 14:05:09 +0300]`), так что fail2ban понимает его при любом `app.timezone`. Логин в строке экранирован: набранный «логин» не подставит в строку чужой адрес.

**«Журнал»** в панели управления — кто и что менял: вход и выход, предметы, фото, разделы, администраторы, а также команды `create-admin`, `passwd`, `unlock` (как «Консоль»). Те же записи попадают строкой `Audit:` в `var/log/estables.log`.

## 10. Резервные копии

- `var/backups/` — дампы базы: перед каждым обновлением схемы (хранятся последние `backup.keep`, 10) и ежесуточные от cron (`backup.keep_daily`, 14). Дампы содержат хеши паролей: права `0600`.
- `public/media/originals/` — оригиналы фото. Они не меняются после загрузки, поэтому копировать их удобно инкрементально. Каталоги уменьшенных копий копировать не нужно: они пересоздаются (`bin/estables media:warm`).
- `config/config.php` — секрет и пароль базы.

Держите копию на другой машине, например так (с машины для копий, по SSH):

```sh
rsync -a --delete сервер:/var/www/estables/var/backups/ ~/estables-backup/db/
rsync -a сервер:/var/www/estables/public/media/originals/ ~/estables-backup/originals/
```

Восстановление: развернуть код (п. 3–5), положить `config/config.php`, загрузить дамп (`gunzip -c дамп.sql.gz | mariadb estables`), вернуть `public/media/originals/` и выдать права `www-data`.

## 11. Обновление до новой сборки

```sh
cd /var/www/estables
git pull                      # или git apply <патч>
composer install --no-dev --optimize-autoloader
sudo -u www-data php bin/estables migrate
```

Если в новой сборке менялись файлы `deploy/nginx/` или `deploy/php-fpm/estables.conf`, скопируйте их заново, как в п. 5. В сборке 5 добавился блок `location ^~ /media/`, в сборке 6 — `server_tokens off`, в сборке 6.1 конфигурация nginx разделена на общую часть (`snippets/estables-app.conf`) и сайты по HTTP и HTTPS. Файлы `deploy/crontab`, `deploy/logrotate` и `deploy/fail2ban` появились в сборке 6 — см. п. 9.

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
| `bin/estables gc` | удаляет неиспользуемые фото, файлы без записи в базе, истёкшие сессии, старые попытки входа и записи журнала (запускает cron) |
| `bin/estables media:warm` | делает все уменьшенные копии фото заранее |
| `bin/estables backup` | делает резервную копию базы в `var/backups/` (запускает cron) |

## Файлы на сервере

- Журнал: `var/log/estables.log` (ротация — п. 9).
- Резервные копии базы: `var/backups/estables-<дата>-before-v<версия>.sql.gz` перед обновлениями схемы и `var/backups/estables-<дата>-daily.sql.gz` ежесуточные (п. 10).

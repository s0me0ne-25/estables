# Установка eStables

Установка с нуля на VPS с Debian 13 (trixie): nginx, php8.4-fpm, MariaDB 11.8, HTTPS от Let's Encrypt. Обновление уже установленного сайта — в [UPGRADE.md](UPGRADE.md). Упрощённый вариант для песочницы без HTTPS — [SANDBOX.md](SANDBOX.md).

Условности:

- все команды выполняются под root;
- сайт живёт в `/var/www/estables`, php-fpm работает от `www-data`;
- `example.org` в командах и файлах — замените на свой домен; DNS-записи A (и AAAA, если есть IPv6) для `example.org` и `www.example.org` уже указывают на сервер;
- консольные команды движка запускаются от `www-data` (`sudo -u www-data php bin/estables …`), иначе файлы в `var/` станут принадлежать root и сайт не сможет в них писать.

## 1. Пакеты

```sh
apt update
apt install sudo nginx php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-intl php8.4-imagick \
    mariadb-server mariadb-client libjpeg-turbo-progs composer git unzip certbot
```

- `sudo` в минимальной установке Debian нет, а он нужен для команд от `www-data`.
- `php8.4-imagick` обязателен: без него не обработать фотографии.
- `libjpeg-turbo-progs` (утилита `jpegtran`) очищает JPEG от метаданных и поворачивает без пересжатия; без него движок работает, но пересжимает повёрнутые снимки.
- `certbot` выдаёт и продлевает сертификат HTTPS (п. 6).

Проверка: `php -v` показывает 8.4, `mariadb --version` — 11.8.

## 2. База данных

Придумайте пароль пользователя базы (его же впишете в `config/config.php`):

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
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader

# Каталоги, в которые пишет сайт
mkdir -p var public/media
chown -R www-data:www-data var public/media
```

Если репозиторий приватный, `git clone` спросит логин и токен GitHub. `composer install` ничего не скачивает (у движка нет сторонних библиотек), он только строит автозагрузку классов; `COMPOSER_ALLOW_SUPERUSER=1` убирает вопрос о запуске под root.

## 4. Конфигурация

```sh
cp config/config.template.php config/config.php
chown root:www-data config/config.php
chmod 640 config/config.php
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'    # случайная строка для app.secret
nano config/config.php
```

Заполните:

- `app.base_url` — `https://example.org` (без косой черты в конце);
- `app.secret` — строка из команды выше;
- `db.password` — пароль из п. 2 (`db.name` и `db.user` в шаблоне уже `estables`).

Остальные настройки можно не трогать: у каждой есть пояснение в файле, отсутствующие берутся по умолчанию. Файл `config/config.php` в git не попадает.

## 5. php-fpm и nginx

```sh
cd /var/www/estables
cp deploy/php-fpm/estables.conf /etc/php/8.4/fpm/pool.d/estables.conf
systemctl reload php8.4-fpm

# Общая часть сайта: корень, статика, фото, PHP
cp deploy/nginx/estables-app.conf /etc/nginx/snippets/estables-app.conf

# Пока нет сертификата — сайт по HTTP; на нём Let's Encrypt проверит домен
mkdir -p /var/www/acme-stub
cp deploy/nginx/estables-http.conf /etc/nginx/sites-available/estables
sed -i 's/example\.org/ВАШ-ДОМЕН/g' /etc/nginx/sites-available/estables
ln -s ../sites-available/estables /etc/nginx/sites-enabled/estables
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
```

Если у сервера есть IPv6, добавьте в каждый блок `server` строку `listen [::]:80;` (а в п. 6 — ещё `listen [::]:443 ssl;`). Если сайт лежит не в `/var/www/estables`, поправьте `root` в `/etc/nginx/snippets/estables-app.conf`.

Откройте `http://example.org/` — должна появиться страница «Первый администратор». Администратора пока не создавайте: пароль не должен идти по открытому HTTP.

## 6. HTTPS

```sh
certbot certonly --webroot -w /var/www/acme-stub -d example.org -d www.example.org

cp /var/www/estables/deploy/nginx/estables.conf /etc/nginx/sites-available/estables
sed -i 's/example\.org/ВАШ-ДОМЕН/g' /etc/nginx/sites-available/estables
nginx -t && systemctl reload nginx

# У пакета certbot есть свой таймер продления; продлевать будет строка в /etc/crontab (п. 8)
systemctl disable --now certbot.timer
```

При первом запуске certbot спросит адрес почты для уведомлений и согласие с условиями. Конфигурацию nginx он не трогает: файлы проверки кладёт в `/var/www/acme-stub`, сертификат — в `/etc/letsencrypt/live/<домен>/`. После этого HTTP отвечает только на проверки Let's Encrypt, всё остальное перенаправляет на `https://<домен>`.

По HTTPS движок отправляет заголовок HSTS (`security.hsts_max_age`, по умолчанию год): после первого захода браузер больше не откроет сайт по HTTP.

## 7. Первый администратор

Схема базы создаётся сама при первом открытии сайта или командой `migrate`. Администратора можно создать двумя способами.

**Из консоли:**

```sh
cd /var/www/estables
sudo -u www-data php bin/estables migrate
sudo -u www-data php bin/estables create-admin
```

**Через браузер** (только по HTTPS): `https://example.org/` покажет форму «Первый администратор». Она защищена одноразовым токеном, который лежит только на сервере:

```sh
cat /var/www/estables/var/install.token
```

После создания первого администратора форма становится недоступной. Войти в панель управления — ссылка «Вход администратора» в подвале сайта или адрес `/login/`. Остальных администраторов добавляют в панели, в разделе «Пользователи».

## 8. Обслуживание: cron, ротация журнала, fail2ban

**cron.** Все задания — в общем `/etc/crontab`; строки для него лежат в `deploy/crontab`. Допишите их в конец файла (`nano /etc/crontab`):

```
37  3  *   *   *   www-data  /usr/bin/php /var/www/estables/bin/estables backup > /dev/null
17  4  *   *   *   www-data  /usr/bin/php /var/www/estables/bin/estables gc > /dev/null
0   3  *   *   *   root      /usr/bin/certbot renew -w /var/www/acme-stub --post-hook "/usr/bin/systemctl reload nginx"
```

- `backup` — ночной дамп базы в `var/backups/` (п. 10);
- `gc` удаляет фото, которые больше нигде не используются (не раньше чем через сутки), истёкшие сессии, старые попытки входа и записи «Журнала» старше двух лет;
- `certbot renew` продлевает сертификат, когда до конца срока остаётся меньше 30 дней.

Время — по часовому поясу сервера (`timedatectl`). cron перечитывает `/etc/crontab` сам; ошибки уходят в почту cron, обычный вывод отбрасывается. Проверить вручную: `sudo -u www-data php bin/estables backup` и `… gc`, `certbot renew --dry-run`.

**Ротация журнала** `var/log/estables.log` — раз в неделю, полгода истории:

```sh
cp /var/www/estables/deploy/logrotate/estables /etc/logrotate.d/estables
logrotate --debug /etc/logrotate.d/estables     # проверка без изменений
```

**fail2ban** закрывает на час адрес, с которого за 10 минут было 10 неудачных входов:

```sh
apt install fail2ban
cp /var/www/estables/deploy/fail2ban/filter.d/estables.conf /etc/fail2ban/filter.d/
cp /var/www/estables/deploy/fail2ban/jail.d/estables.conf /etc/fail2ban/jail.d/
fail2ban-client reload
fail2ban-client status estables
```

После пары неудачных входов фильтр можно проверить на журнале: `fail2ban-regex /var/www/estables/var/log/estables.log /etc/fail2ban/filter.d/estables.conf`.

## 9. Проверка после установки

- `sudo -u www-data php bin/estables check` — все строки «ok»;
- `http://example.org/` перенаправляет на `https://example.org/`, в браузере замок без предупреждений;
- в панели управления: создать раздел, предмет, загрузить фото — фото видно на сайте, «Скачать оригинал» отдаёт файл;
- «Журнал» в панели показывает эти действия;
- `https://example.org/robots.txt` и `/sitemap.xml` открываются, адреса в них начинаются с `https://example.org`;
- `ls /var/www/estables/var/backups/` на следующее утро — дамп `…-daily.sql.gz`.

## 10. Резервные копии

Что копировать:

- `var/backups/` — дампы базы: ночные (хранятся последние `backup.keep_daily`, 14) и перед каждым обновлением схемы (`backup.keep`, 10). Дампы содержат хеши паролей, права у них `0600`;
- `public/media/originals/` — оригиналы фото. Они не меняются после загрузки, поэтому их удобно копировать инкрементально. Каталоги уменьшенных копий копировать не нужно: они пересоздаются;
- `config/config.php` — секрет и пароль базы.

Держите копию на другой машине, например так (с машины для копий, по SSH):

```sh
rsync -a --delete root@example.org:/var/www/estables/var/backups/ ~/estables-backup/db/
rsync -a root@example.org:/var/www/estables/public/media/originals/ ~/estables-backup/originals/
scp root@example.org:/var/www/estables/config/config.php ~/estables-backup/
```

**Восстановление** на новом сервере: пп. 1–3 и 5–6, затем вернуть `config/config.php` (п. 4 — права), загрузить дамп и фото:

```sh
gunzip -c estables-…-daily.sql.gz | mariadb estables
rsync -a ~/estables-backup/originals/ /var/www/estables/public/media/originals/
chown -R www-data:www-data /var/www/estables/public/media
sudo -u www-data php /var/www/estables/bin/estables migrate
sudo -u www-data php /var/www/estables/bin/estables media:warm
```

## Настройки

Все настройки — в `config/config.php`, с пояснениями в `config/config.template.php`. Самые нужные:

- **Вход** (`session`, `login`). В cookie лежит только случайный идентификатор сессии, в базе — его SHA-256. Без «Запомнить меня» вход живёт до закрытия браузера и завершается на сервере после 12 часов без активности; с отметкой — 30 дней, срок продлевается при каждом заходе. После 5 неудачных попыток для логина или 20 с одного адреса форма закрывается на 15 минут. Каждая неудача пишется в `var/log/estables.log` строкой `Login failed for "<логин>" from <IP>` — по ней работает fail2ban.
- **Капча** (`captcha`). `stub` — простая галочка без настоящей проверки (для разработки или когда сайт закрыт Anubis/fail2ban); `turnstile` — [Cloudflare Turnstile](https://developers.cloudflare.com/turnstile/): создайте виджет в панели Cloudflare и впишите `site_key` и `secret_key`.
- **Фото** (`media`). Загрузка до 50 МБ и 100 Мп; это значение должно укладываться в `upload_max_filesize` пула php-fpm и `client_max_body_size` nginx (в конфигурациях из `deploy/` — 50 и 64 МБ). Копии 400 и 1600 px по длинной стороне делаются при первом запросе; после смены размеров сделайте их заново командой `media:warm`, старые каталоги копий в `public/media/` можно удалить. Оригинал до 2560 px показывается сам, без копии.
- **Журнал** (`audit`) — срок хранения записей, два года.
- **HSTS** (`security.hsts_max_age`) — `0` отключает.

## Команды

Запуск: `sudo -u www-data php /var/www/estables/bin/estables <команда>`.

| Команда | Что делает |
|---|---|
| `check` | проверяет PHP, расширения, права на запись и подключение к MariaDB |
| `migrate` | создаёт или обновляет схему базы |
| `status` | показывает версию схемы в базе и в коде |
| `create-admin [логин] [--name="Имя"] [--password-stdin]` | создаёт администратора |
| `passwd <логин> [--password-stdin]` | меняет пароль администратора и завершает все его сессии (если пароль забыт) |
| `unlock <логин>` | сбрасывает неудачные попытки входа, снимая блокировку логина |
| `backup` | делает дамп базы в `var/backups/` (запускает cron) |
| `gc` | удаляет неиспользуемые фото, файлы без записи в базе, истёкшие сессии, старые попытки входа и записи журнала (запускает cron) |
| `media:warm` | делает все уменьшенные копии фото заранее |

## Где что лежит

| Путь | Что там |
|---|---|
| `/var/www/estables/config/config.php` | настройки |
| `/var/www/estables/var/log/estables.log` | журнал движка: ошибки, входы, строки `Audit:` |
| `/var/www/estables/var/backups/` | дампы базы |
| `/var/www/estables/public/media/originals/` | оригиналы фото |
| `/etc/nginx/snippets/estables-app.conf`, `/etc/nginx/sites-available/estables` | nginx |
| `/etc/php/8.4/fpm/pool.d/estables.conf` | пул php-fpm |
| `/etc/crontab` | задания cron |
| `/var/log/nginx/estables.*.log` | журналы nginx |

## Если что-то не работает

```sh
sudo -u www-data php /var/www/estables/bin/estables check   # сервер и база
tail -n 50 /var/www/estables/var/log/estables.log            # журнал движка
tail -n 50 /var/log/nginx/estables.error.log                 # ошибки nginx
tail -n 50 /var/log/php8.4-fpm.log                           # ошибки php-fpm
```

- **502 Bad Gateway** — не запущен пул php-fpm: `systemctl status php8.4-fpm`.
- **«Нет связи с базой данных»** — MariaDB (`systemctl status mariadb`) и пароль в `config/config.php`.
- **«Ошибка на сервере»** — причина записана в `var/log/estables.log`. На время разбора можно включить `app.debug`, но не оставляйте его на открытом сайте.
- **certbot не может проверить домен** — DNS указывает не на этот сервер, или порт 80 закрыт снаружи.

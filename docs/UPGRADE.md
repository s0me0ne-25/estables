# Обновление eStables

Как обновить сайт, установленный по [INSTALL.md](INSTALL.md), и как откатиться, если что-то пошло не так. Условности те же: команды под root, сайт в `/var/www/estables`, команды движка — от `www-data`.

## Коротко

```sh
cd /var/www/estables
git fetch
git log --oneline HEAD..origin/main                                        # что придёт
git diff --stat HEAD origin/main -- deploy/ config/config.template.php     # менялись ли файлы сервера
git rev-parse --short HEAD > /root/estables-before-upgrade                 # куда откатываться

git pull --ff-only
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader
sudo -u www-data php bin/estables migrate
sudo -u www-data php bin/estables check
systemctl reload php8.4-fpm
```

Затем — п. 3, если `git diff --stat` показал изменения в `deploy/`.

## 1. Перед обновлением

- Что изменилось — в `git log` и в [BUILDS.md](BUILDS.md) (раздел новой сборки: что работает, как проверить).
- Резервную копию базы движок делает сам перед изменением схемы (`var/backups/estables-<дата>-before-v<версия>.sql.gz`); если её не удалось сделать, схема не обновляется. Фото при обновлении не трогаются.
- Если обновление крупное или после него нужно что-то проверить до посетителей, включите режим техработ: панель управления → «Пользователи» → внизу «Режим техработ». Посетители увидят заглушку, администраторы — сайт целиком; после проверки режим выключают там же.
- Файлы движка на сервере руками не правьте: `git pull --ff-only` откажется обновлять изменённые файлы. Если всё же правили, `git status` покажет что, а `git stash` уберёт правки (вернуть: `git stash pop`).

## 2. Обновление

```sh
cd /var/www/estables
git rev-parse --short HEAD > /root/estables-before-upgrade
git pull --ff-only
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader
sudo -u www-data php bin/estables migrate
sudo -u www-data php bin/estables check
systemctl reload php8.4-fpm
```

- `composer install` нужен после каждого обновления: он перестраивает карту классов, без неё новые классы не найдутся.
- `migrate` обновляет схему базы и пишет «Схема обновлена: версия N → M» или «Схема уже актуальна». Если его забыть, схема обновится сама при первом запросе к сайту; на эти секунды посетители увидят страницу «Идёт обновление».
- `reload php8.4-fpm` сбрасывает кеш скомпилированного PHP (opcache), чтобы все процессы сразу работали с новыми файлами.

## 3. Если менялись файлы сервера

`git diff --stat` из раздела «Коротко» (или `git diff --stat $(cat /root/estables-before-upgrade) HEAD -- deploy/` после обновления) показывает, что изменилось в `deploy/`. Скопируйте изменённые файлы туда же, куда при установке:

| Файл в `deploy/` | Куда | Затем |
|---|---|---|
| `nginx/estables-app.conf` | `/etc/nginx/snippets/estables-app.conf` | `nginx -t && systemctl reload nginx` |
| `nginx/estables.conf` | `/etc/nginx/sites-available/estables`, заменив `example.org` на свой домен | `nginx -t && systemctl reload nginx` |
| `nginx/estables-http.conf` | только на сайте без сертификата (песочница): `/etc/nginx/sites-available/estables` | `nginx -t && systemctl reload nginx` |
| `php-fpm/estables.conf` | `/etc/php/8.4/fpm/pool.d/estables.conf` | `systemctl reload php8.4-fpm` |
| `crontab` | поправить строки в `/etc/crontab` | — |
| `logrotate/estables` | `/etc/logrotate.d/estables` | — |
| `fail2ban/filter.d/estables.conf`, `fail2ban/jail.d/estables.conf` | `/etc/fail2ban/filter.d/`, `/etc/fail2ban/jail.d/` | `fail2ban-client reload` |

`config/config.template.php` в изменениях означает новые настройки. Переносить их в `config/config.php` не обязательно: отсутствующие берутся по умолчанию. Посмотреть, что добавилось: `git diff $(cat /root/estables-before-upgrade) HEAD -- config/config.template.php`.

## 4. Проверка

- `sudo -u www-data php bin/estables status` — версии схемы в базе и в коде совпадают;
- сайт открывается, вход в панель работает;
- что именно проверить в новой сборке — в её разделе [BUILDS.md](BUILDS.md);
- `tail -n 20 var/log/estables.log` — без новых `ERROR`.

## 5. Откат

Если новая версия работает не так, как нужно, вернитесь к прежней.

**Схема базы не менялась** (`migrate` написал «Схема уже актуальна»):

```sh
cd /var/www/estables
git checkout $(cat /root/estables-before-upgrade)
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader
rm -rf var/cache/*
systemctl reload php8.4-fpm
```

**Схема менялась** — прежний код с новой базой не работает (сайт покажет «База данных новее кода»), поэтому базу возвращают из копии, сделанной перед обновлением. Всё, что изменили на сайте после обновления, при этом пропадёт.

```sh
cd /var/www/estables
systemctl stop php8.4-fpm                               # на время отката сайт отвечает 502
ls -t var/backups/*-before-v*.sql.gz | head -1          # копия перед обновлением
git checkout $(cat /root/estables-before-upgrade)
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader
mariadb -e "DROP DATABASE estables; CREATE DATABASE estables CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci;"
gunzip -c var/backups/estables-<дата>-before-v<версия>.sql.gz | mariadb estables
rm -rf var/cache/*
systemctl start php8.4-fpm
sudo -u www-data php bin/estables status
```

php-fpm останавливают на время отката не случайно: процессы, у которых в памяти ещё новый код, первым же запросом снова обновили бы восстановленную базу.

Права пользователя `estables` на базу при её пересоздании сохраняются. Фото, загруженные после обновления, остались на диске, но в восстановленной базе их нет: `gc` удалит эти файлы.

`git checkout` к старому коммиту оставляет рабочую копию «вне ветки». Когда исправленная версия выйдет, вернитесь на ветку и обновитесь как обычно: `git checkout main`, затем раздел 2.

## Переход с промежуточных сборок

Касается сайтов, поставленных до выпуска (песочница). Кроме обычного обновления:

- **со сборки 4 и раньше:** нужен `php8.4-imagick` (`apt install php8.4-imagick`, затем `systemctl reload php8.4-fpm`), в nginx появился блок `/media/`;
- **со сборки 5 и раньше:** строки `backup` и `gc` для `/etc/crontab`, ротация журнала и fail2ban — INSTALL.md, п. 8;
- **со сборки 6 (до 6.1):** конфигурация nginx разделена на `snippets/estables-app.conf` и сайт (`estables-http.conf` без сертификата или `estables.conf` с HTTPS) — поставьте оба файла по INSTALL.md, п. 5–6. Таймеры systemd заменены строками `/etc/crontab`: `systemctl disable --now estables-gc.timer estables-backup.timer`, `rm /etc/systemd/system/estables-*`, `systemctl daemon-reload`.

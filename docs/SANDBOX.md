# LNMP-песочница для проверки сборок

Песочница — виртуальная машина с чистым Debian 13, на которой каждая сборка проверяется в браузере. Здесь всё сделано максимально просто: вход под root по SSH, без HTTPS, без IPv6, сайт открывается по IP-адресу виртуалки. Для боевого сервера используйте [INSTALL.md](INSTALL.md).

Все команды выполняются под root.

## 1. Пакеты

```sh
apt update
apt install -y nginx php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-intl php8.4-imagick \
    mariadb-server mariadb-client libjpeg-turbo-progs composer git unzip
```

Проверка: `php -v` показывает 8.4, `mariadb --version` — 11.8.

## 2. База данных

```sh
mariadb -e "
CREATE DATABASE estables CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci;
CREATE USER 'estables'@'localhost' IDENTIFIED BY 'estables';
GRANT ALL PRIVILEGES ON estables.* TO 'estables'@'localhost';"
```

## 3. Код

```sh
git clone https://github.com/s0me0ne-25/estables.git /var/www/estables
cd /var/www/estables
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader
mkdir -p var public/media
chown -R www-data:www-data var public/media
```

Если репозиторий приватный, `git clone` спросит логин и токен GitHub.

## 4. php-fpm и nginx

```sh
cd /var/www/estables
cp deploy/php-fpm/estables.conf /etc/php/8.4/fpm/pool.d/
systemctl restart php8.4-fpm

# Конфиг из репозитория: без IPv6, отвечает на любой адрес и IP
sed -e '/listen \[::\]:80;/d' \
    -e 's/listen 80;/listen 80 default_server;/' \
    -e 's/server_name estables.example.org;/server_name _;/' \
    deploy/nginx/estables.conf > /etc/nginx/sites-available/estables
ln -sf /etc/nginx/sites-available/estables /etc/nginx/sites-enabled/estables
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

hostname -I   # IP виртуалки
```

Откройте `http://<IP>/` — страница «Установка eStables»: конфигурации ещё нет.

## 5. Конфигурация

```sh
cd /var/www/estables
IP=$(hostname -I | awk '{print $1}')
SECRET=$(php -r 'echo bin2hex(random_bytes(32));')
cat > config/config.php <<EOF
<?php
return [
    'app' => [
        'base_url' => 'http://$IP',
        'secret' => '$SECRET',
        'debug' => true,
        'timezone' => 'Europe/Moscow',
    ],
    'db' => [
        'socket' => '/run/mysqld/mysqld.sock',
        'name' => 'estables',
        'user' => 'estables',
        'password' => 'estables',
    ],
];
EOF
```

`debug => true` показывает подробности ошибок прямо на странице: в песочнице это удобно, на боевом сервере — нельзя.

Обновите страницу: схема базы создастся сама, откроется форма «Первый администратор». Токен для неё:

```sh
cat /var/www/estables/var/install.token
```

Дальше — по разделу нужной сборки в [BUILDS.md](BUILDS.md).

Совет: сделайте снимок виртуалки до создания администратора, чтобы быстро возвращаться к установке с нуля.

## 6. Следующие сборки

После коммита сборки:

```sh
cd /var/www/estables
git pull
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader
runuser -u www-data -- php bin/estables migrate
```

`composer install` нужен после каждого обновления: `--optimize-autoloader` строит карту классов, и новые классы без неё не найдутся.

Проверка патча до коммита:

```sh
scp файл.patch root@<IP>:/root/          # с рабочей машины
cd /var/www/estables
git apply /root/файл.patch
# затем composer install и migrate, как выше
```

Перед следующим `git pull` верните рабочую копию к чистому состоянию:

```sh
git reset --hard && git clean -fd
```

`config/config.php`, `var/` и `public/media/` при этом не пострадают: они в `.gitignore`.

Если сборка меняла `deploy/nginx/estables.conf` или `deploy/php-fpm/estables.conf` (так было в сборке 5), повторите команды из п. 4: конфигурации копируются, а не подключаются из рабочей копии.

`migrate` можно не запускать — схема обновится сама при первом запросе, но команда сразу покажет ошибку, если что-то не так. Консольные команды запускайте через `runuser -u www-data --`, иначе файлы в `var/` станут принадлежать root и сайт не сможет в них писать.

## 7. Сброс к чистой установке

```sh
cd /var/www/estables
mariadb -e "DROP DATABASE estables; CREATE DATABASE estables CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci;"
rm -rf var/*
```

Чтобы снова увидеть страницу «нет конфигурации»: `mv config/config.php config/config.php.off` (и обратно).

## 8. Если что-то не работает

```sh
runuser -u www-data -- php /var/www/estables/bin/estables check   # сервер и база
tail -n 50 /var/www/estables/var/log/estables.log                  # журнал движка
tail -n 50 /var/log/nginx/estables.error.log                       # ошибки nginx
tail -n 50 /var/log/php8.4-fpm.log                                 # ошибки php-fpm
```

- **502 Bad Gateway** — не запущен пул php-fpm: `systemctl status php8.4-fpm`.
- **«Нет связи с базой данных»** — проверьте MariaDB (`systemctl status mariadb`) и пароль в `config/config.php`.
- **«Ошибка на сервере»** — причина записана в `var/log/estables.log`; с `debug => true` она видна и на странице.

## Коммиты с Windows

Git на Windows не различает регистр имён файлов и обычно не хранит бит исполнения. Перед коммитом сборки проверьте `git status`: все новые файлы из патча должны быть в списке. Бит исполнения у `bin/estables` можно выставить так:

```sh
git update-index --chmod=+x bin/estables
```

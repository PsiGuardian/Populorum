# Populorum — Установка и обновление

🇷🇺 **Русский · ru_RU**

> Populorum — вымышленная симуляция, дополняющая существующие институты. Голоса не имеют юридической силы. Не вводите настоящие персональные данные или секреты рабочей среды.

## Требования

Нужны PHP 8.4 или новее, Composer и SQLite. В Arch Linux установите пакеты через pacman. Проверьте перечисленные ниже расширения командой `php -m`; в `/etc/php/php.ini` включайте только нужные модули, которые ещё не встроены в PHP.

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

Архив GitHub не содержит `vendor/`. Команда `composer install` устанавливает версии из `composer.lock` и создаёт `vendor/autoload.php`. Для обычной установки не используйте `composer update`.

## Первая установка

Распакуйте архив и откройте терминал в корне репозитория. Если вы уже находитесь в `apps/symfony`, пропустите команду `cd`.

```bash
cd apps/symfony
composer install --no-interaction --prefer-dist &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo &&
php -S 127.0.0.1:5412 -t public
```

Операторы `&&` останавливают последовательность при ошибке: исправьте её, прежде чем продолжать. Откройте http://127.0.0.1:5412/; Ctrl+C останавливает локальный сервер. Инициализация создаёт `.env.local`, только если файл отсутствует. Рабочая база — `var/populorum.sqlite`. Cookies профайлера требуют HTTPS даже для ручной локальной проверки.

## Обновление

Остановите сервер и сохраните резервные копии базы, файлов и ключей перед заменой исходников. Сохраните `.env.local`, `.guardian-shield/` и `var/`. Никогда не заменяйте рабочую базу файлом `data/populorum-demo.sqlite`. Из `apps/symfony` выполните:

```bash
composer install --no-interaction --prefer-dist &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo
```

После успешного завершения всех шагов снова запустите локальный сервер:

```bash
php -S 127.0.0.1:5412 -t public
```

## Устранение неполадок

Если отсутствует `vendor/autoload.php`, остановите сервер и выполните `composer install --no-interaction --prefer-dist` из `apps/symfony`. При сбое исправьте указанную версию PHP или недостающие расширения, прежде чем повторять команды Symfony. Не используйте `--ignore-platform-reqs`. При размещении DocumentRoot должен указывать на `apps/symfony/public/`; используйте PHP-FPM, Apache или Nginx и HTTPS. Встроенный сервер PHP предназначен только для локальных проверок.

## Справочная документация (на французском)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)

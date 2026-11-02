# Populorum — Instalación y actualización

🇪🇸 **Español · es_ES**

> Populorum es una simulación ficticia que complementa a las instituciones existentes. Los votos no tienen efectos jurídicos. No introduzca datos personales reales ni secretos de producción.

## Requisitos

Se necesitan PHP 8.4 o superior, Composer y SQLite. En Arch Linux, instale los paquetes con pacman. Compruebe las extensiones indicadas abajo con `php -m`; active en `/etc/php/php.ini` únicamente los módulos necesarios que no estén ya compilados en PHP.

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

El archivo de GitHub no incluye `vendor/`. `composer install` instala las versiones fijadas en `composer.lock` y crea `vendor/autoload.php`. No utilice `composer update` para una instalación normal.

## Primera instalación

Extraiga el archivo y abra una terminal en la raíz del repositorio. Si ya está en `apps/symfony`, no repita el comando `cd`.

```bash
cd apps/symfony
composer install --no-interaction --prefer-dist &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:geography:install --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo &&
php -S 127.0.0.1:5412 -t public
```

Los operadores `&&` detienen la secuencia si falla un paso: corrija el error antes de continuar. Abra http://127.0.0.1:5412/; Ctrl+C detiene el servidor local. La inicialización crea `.env.local` solo si no existe. La base activa es `var/populorum.sqlite`. Las cookies del profiler requieren HTTPS, incluso en una prueba manual local.

## Actualización

Detenga el servidor y haga una copia de seguridad de la base, los archivos y las claves antes de sustituir las fuentes. Conserve `.env.local`, `.guardian-shield/` y `var/`. Nunca sustituya una base activa por `data/populorum-demo.sqlite`. Desde `apps/symfony`, ejecute:

```bash
composer install --no-interaction --prefer-dist &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:geography:install --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo
```

Cuando todos los pasos terminen correctamente, reinicie el servidor local:

```bash
php -S 127.0.0.1:5412 -t public
```

## Resolución de problemas

Si falta `vendor/autoload.php`, detenga el servidor y ejecute `composer install --no-interaction --prefer-dist` desde `apps/symfony`. Si falla, corrija la versión de PHP o las extensiones indicadas antes de volver a ejecutar los comandos Symfony. No utilice `--ignore-platform-reqs`. Para el alojamiento, el DocumentRoot debe ser `apps/symfony/public/`, con PHP-FPM, Apache o Nginx y HTTPS; el servidor integrado de PHP es solo para pruebas locales.

## Documentación de referencia (francés)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)

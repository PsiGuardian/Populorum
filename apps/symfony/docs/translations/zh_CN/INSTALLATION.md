# Populorum — 安装与更新

🇨🇳 **简体中文 · zh_CN**

> Populorum 是补充现有机构的虚构模拟项目。投票不具有法律效力。请勿输入真实个人信息或生产环境的机密数据。

## 前提条件

需要 PHP 8.4 或更高版本、Composer 和 SQLite。在 Arch Linux 上通过 pacman 安装软件包。使用 `php -m` 检查下列扩展；仅当所需模块尚未编译进 PHP 时，才在 `/etc/php/php.ini` 中启用。

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

GitHub 压缩包不包含 `vendor/`。`composer install` 会安装 `composer.lock` 锁定的版本并生成 `vendor/autoload.php`。正常安装时不要使用 `composer update`。

## 首次安装

解压压缩包，在仓库根目录打开终端。如果已位于 `apps/symfony`，请跳过 `cd` 命令。

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

任何一步失败时，`&&` 都会停止后续命令；修复错误后再继续。打开 http://127.0.0.1:5412/；按 Ctrl+C 停止本地服务器。初始化仅在 `.env.local` 不存在时创建该文件。运行中的数据库为 `var/populorum.sqlite`。Profiler 的 Cookie 需要 HTTPS，即使是手动本地测试也一样。

## 更新

替换源代码前，停止服务器并备份数据库、文件和密钥。保留 `.env.local`、`.guardian-shield/` 和 `var/`。切勿用 `data/populorum-demo.sqlite` 替换正在使用的数据库。在 `apps/symfony` 中执行：

```bash
composer install --no-interaction --prefer-dist &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:geography:install --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo
```

所有步骤成功完成后，重新启动本地服务器：

```bash
php -S 127.0.0.1:5412 -t public
```

## 故障排查

如果找不到 `vendor/autoload.php`，请停止服务器，然后在 `apps/symfony` 中运行 `composer install --no-interaction --prefer-dist`。如果失败，请先解决提示的 PHP 版本或扩展问题，再运行 Symfony 命令。不要使用 `--ignore-platform-reqs`。对外部署时，DocumentRoot 必须指向 `apps/symfony/public/`，并使用 PHP-FPM、Apache 或 Nginx 以及 HTTPS；PHP 内置服务器仅适用于本地测试。

## 参考文档（法语）

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)

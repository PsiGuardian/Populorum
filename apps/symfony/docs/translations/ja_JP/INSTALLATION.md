# Populorum — 初回インストール

🇯🇵 **日本語 · ja_JP**

> Populorum は既存の制度を補完する架空のシミュレーションです。投票に法的効力はありません。実在する個人の情報や本番環境の秘密情報を入力しないでください。

## 前提条件

PHP 8.4 以降、Composer、SQLite が必要です。Arch Linux では pacman でパッケージをインストールします。以下の拡張機能を `php -m` で確認し、必要なモジュールが PHP に組み込まれていない場合にのみ `/etc/php/php.ini` で有効にしてください。

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

GitHub のアーカイブには `vendor/` が含まれていません。`composer install` は `composer.lock` に固定されたバージョンをインストールし、`vendor/autoload.php` を作成します。通常のインストールでは `composer update` を使用しないでください。

## 初回インストール

アーカイブを展開し、リポジトリのルートでターミナルを開きます。すでに `apps/symfony` にいる場合は、`cd` コマンドを省略してください。

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

`&&` により、いずれかの手順でエラーが発生すると処理が止まります。修正してから続けてください。http://127.0.0.1:5412/ を開きます。Ctrl+C でローカルサーバーを停止できます。初期化時に `.env.local` が存在しない場合のみ作成されます。稼働中のデータベースは `var/populorum.sqlite` です。プロファイラーの Cookie には、手動のローカルテストでも HTTPS が必要です。

## トラブルシューティング

`vendor/autoload.php` が見つからない場合はサーバーを停止し、`apps/symfony` から `composer install --no-interaction --prefer-dist` を実行してください。失敗した場合は、表示された PHP のバージョンや不足する拡張機能の問題を解決してから Symfony のコマンドを再実行してください。`--ignore-platform-reqs` は使用しないでください。公開サーバーでは DocumentRoot を `apps/symfony/public/` に設定し、PHP-FPM、Apache または Nginx、および HTTPS を使用します。PHP の組み込みサーバーはローカルテスト専用です。

## 参照ドキュメント（フランス語）

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)

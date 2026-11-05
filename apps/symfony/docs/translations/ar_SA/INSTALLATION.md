# Populorum — التثبيت الأول

🇸🇦 **العربية · ar_SA**

> Populorum محاكاة خيالية مكمّلة للمؤسسات القائمة. لا يترتب على التصويت أي أثر قانوني. لا تُدخل بيانات شخصية حقيقية أو أسرار بيئة الإنتاج.

## المتطلبات

يلزم PHP 8.4 أو أحدث، وComposer وSQLite. على Arch Linux، ثبّت الحزم باستخدام pacman. تحقّق من الامتدادات أدناه بالأمر `php -m`؛ ولا تفعّل في `/etc/php/php.ini` إلا الوحدات اللازمة غير المضمّنة أصلًا في PHP.

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

لا يتضمن أرشيف GitHub المجلد `vendor/`. يثبّت الأمر `composer install` الإصدارات المحددة في `composer.lock` وينشئ `vendor/autoload.php`. لا تستخدم `composer update` للتثبيت العادي.

## التثبيت الأول

فكّ ضغط الأرشيف وافتح الطرفية في جذر المستودع. إذا كنت داخل `apps/symfony` بالفعل، فلا تكرر الأمر `cd`.

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

توقف عوامل `&&` تسلسل الأوامر عند حدوث خطأ؛ أصلحه قبل المتابعة. افتح http://127.0.0.1:5412/؛ ويوقف Ctrl+C الخادم المحلي. تنشئ التهيئة `.env.local` فقط إذا لم يكن موجودًا. قاعدة البيانات النشطة هي `var/populorum.sqlite`. تتطلب ملفات تعريف الارتباط الخاصة بالـ profiler اتصال HTTPS، حتى في اختبار محلي يدوي.

## استكشاف الأخطاء وإصلاحها

إذا كان `vendor/autoload.php` مفقودًا، فأوقف الخادم ونفّذ `composer install --no-interaction --prefer-dist` داخل `apps/symfony`. عند الفشل، صحّح إصدار PHP أو الامتدادات المذكورة قبل إعادة تشغيل أوامر Symfony. لا تستخدم `--ignore-platform-reqs`. للاستضافة، يجب أن يكون DocumentRoot هو `apps/symfony/public/`، مع PHP-FPM وApache أو Nginx وHTTPS؛ خادم PHP المدمج مخصص للاختبارات المحلية فقط.

## الوثائق المرجعية (بالفرنسية)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)

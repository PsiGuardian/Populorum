# Populorum — स्थापना और अद्यतन

🇮🇳 **हिन्दी · hi_IN**

> Populorum मौजूदा संस्थाओं का पूरक एक काल्पनिक अनुकरण है। मतों का कोई कानूनी प्रभाव नहीं है। वास्तविक व्यक्तिगत डेटा या उत्पादन परिवेश के गोपनीय विवरण दर्ज न करें।

## आवश्यकताएँ

PHP 8.4 या नया संस्करण, Composer और SQLite आवश्यक हैं। Arch Linux पर pacman से पैकेज स्थापित करें। नीचे दिए गए एक्सटेंशन `php -m` से जाँचें; `/etc/php/php.ini` में केवल आवश्यक मॉड्यूल सक्रिय करें जो पहले से PHP में संकलित नहीं हैं।

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

GitHub संग्रह में `vendor/` शामिल नहीं है। `composer install`, `composer.lock` में निर्धारित संस्करण स्थापित करता है और `vendor/autoload.php` बनाता है। सामान्य स्थापना के लिए `composer update` का उपयोग न करें।

## पहली स्थापना

संग्रह निकालें और रिपॉज़िटरी के मूल फ़ोल्डर में टर्मिनल खोलें। यदि आप पहले से `apps/symfony` में हैं, तो `cd` कमांड दोबारा न चलाएँ।

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

किसी चरण में त्रुटि होने पर `&&` क्रम रोक देता है; आगे बढ़ने से पहले त्रुटि ठीक करें। http://127.0.0.1:5412/ खोलें; Ctrl+C स्थानीय सर्वर रोकता है। प्रारंभिक सेटअप `.env.local` तभी बनाता है जब वह मौजूद नहीं है। सक्रिय डेटाबेस `var/populorum.sqlite` है। प्रोफाइलर की कुकीज़ के लिए HTTPS आवश्यक है, स्थानीय मैनुअल परीक्षण में भी।

## अद्यतन

स्रोत फ़ाइलें बदलने से पहले सर्वर रोकें और डेटाबेस, फ़ाइलों तथा कुंजियों का बैकअप लें। `.env.local`, `.guardian-shield/` और `var/` सुरक्षित रखें। सक्रिय डेटाबेस को कभी `data/populorum-demo.sqlite` से न बदलें। `apps/symfony` से चलाएँ:

```bash
composer install --no-interaction --prefer-dist &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:geography:install --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo
```

सभी चरण सफल होने के बाद स्थानीय सर्वर फिर से शुरू करें:

```bash
php -S 127.0.0.1:5412 -t public
```

## समस्या निवारण

यदि `vendor/autoload.php` नहीं मिलता, तो सर्वर रोकें और `apps/symfony` से `composer install --no-interaction --prefer-dist` चलाएँ। असफल होने पर Symfony कमांड दोबारा चलाने से पहले बताई गई PHP संस्करण या एक्सटेंशन की समस्या ठीक करें। `--ignore-platform-reqs` का उपयोग न करें। होस्टिंग के लिए DocumentRoot `apps/symfony/public/` होना चाहिए; PHP-FPM, Apache या Nginx और HTTPS का उपयोग करें। PHP का अंतर्निहित सर्वर केवल स्थानीय परीक्षण के लिए है।

## संदर्भ दस्तावेज़ (फ़्रेंच)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)

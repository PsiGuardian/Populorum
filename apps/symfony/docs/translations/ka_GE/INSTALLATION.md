# Populorum — ინსტალაცია და განახლება

🇬🇪 **ქართული · ka_GE**

> Populorum არის გამოგონილი სიმულაცია, რომელიც არსებულ ინსტიტუტებს ავსებს. ხმებს სამართლებრივი ძალა არ აქვს. არ შეიყვანოთ რეალური პერსონალური მონაცემები ან საწარმოო გარემოს საიდუმლო ინფორმაცია.

## წინაპირობები

საჭიროა PHP 8.4 ან უფრო ახალი ვერსია, Composer და SQLite. Arch Linux-ზე პაკეტები დააყენეთ pacman-ით. ქვემოთ ჩამოთვლილი გაფართოებები შეამოწმეთ `php -m` ბრძანებით; `/etc/php/php.ini`-ში ჩართეთ მხოლოდ საჭირო მოდულები, რომლებიც PHP-ში უკვე ჩაშენებული არ არის.

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

GitHub-ის არქივი არ შეიცავს `vendor/` საქაღალდეს. `composer install` აყენებს `composer.lock`-ში დაფიქსირებულ ვერსიებს და ქმნის `vendor/autoload.php` ფაილს. ჩვეულებრივი ინსტალაციისთვის არ გამოიყენოთ `composer update`.

## პირველი ინსტალაცია

გახსენით არქივი და ტერმინალი გაუშვით რეპოზიტორიის ძირეულ საქაღალდეში. თუ უკვე `apps/symfony`-ში ხართ, გამოტოვეთ `cd` ბრძანება.

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

შეცდომის შემთხვევაში `&&` ბრძანებების მიმდევრობას აჩერებს: გაგრძელებამდე გამოასწორეთ შეცდომა. გახსენით http://127.0.0.1:5412/; Ctrl+C აჩერებს ლოკალურ სერვერს. ინიციალიზაცია `.env.local`-ს ქმნის მხოლოდ მაშინ, თუ ფაილი არ არსებობს. აქტიური მონაცემთა ბაზაა `var/populorum.sqlite`. პროფაილერის ქუქიებს HTTPS სჭირდება, ხელით შესრულებული ლოკალური ტესტის დროსაც.

## განახლება

წყაროების შეცვლამდე გააჩერეთ სერვერი და შექმენით მონაცემთა ბაზის, ფაილებისა და გასაღებების სარეზერვო ასლები. შეინარჩუნეთ `.env.local`, `.guardian-shield/` და `var/`. აქტიური მონაცემთა ბაზა არასოდეს ჩაანაცვლოთ `data/populorum-demo.sqlite` ფაილით. `apps/symfony`-დან გაუშვით:

```bash
composer install --no-interaction --prefer-dist &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:geography:install --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo
```

ყველა ნაბიჯის წარმატებით დასრულების შემდეგ ხელახლა გაუშვით ლოკალური სერვერი:

```bash
php -S 127.0.0.1:5412 -t public
```

## პრობლემების მოგვარება

თუ `vendor/autoload.php` ვერ მოიძებნა, გააჩერეთ სერვერი და `apps/symfony`-დან გაუშვით `composer install --no-interaction --prefer-dist`. წარუმატებლობისას Symfony-ის ბრძანებების ხელახლა გაშვებამდე გამოასწორეთ მითითებული PHP-ის ვერსია ან გაფართოებები. არ გამოიყენოთ `--ignore-platform-reqs`. ჰოსტინგისთვის DocumentRoot უნდა იყოს `apps/symfony/public/`; გამოიყენეთ PHP-FPM, Apache ან Nginx და HTTPS. PHP-ის ჩაშენებული სერვერი მხოლოდ ლოკალური ტესტებისთვისაა.

## საცნობარო დოკუმენტაცია (ფრანგულად)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)

# Populorum — Cài đặt và cập nhật

🇻🇳 **Tiếng Việt · vi_VN**

> Populorum là mô phỏng hư cấu, bổ trợ cho các thể chế hiện có. Phiếu bầu không có hiệu lực pháp lý. Không nhập dữ liệu cá nhân thật hoặc thông tin bí mật của môi trường vận hành.

## Yêu cầu

Cần PHP 8.4 trở lên, Composer và SQLite. Trên Arch Linux, cài các gói bằng pacman. Kiểm tra các phần mở rộng bên dưới bằng `php -m`; chỉ bật trong `/etc/php/php.ini` những mô-đun cần thiết chưa được tích hợp sẵn vào PHP.

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

Tệp nén GitHub không chứa `vendor/`. `composer install` cài các phiên bản được khóa trong `composer.lock` và tạo `vendor/autoload.php`. Không dùng `composer update` cho cài đặt thông thường.

## Cài đặt lần đầu

Giải nén và mở terminal tại thư mục gốc của kho mã. Nếu đã ở trong `apps/symfony`, bỏ qua lệnh `cd`.

```bash
cd apps/symfony
composer install --no-interaction --prefer-dist &&
php tools/init-demo.php &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo &&
php -S 127.0.0.1:5412 -t public
```

Toán tử `&&` dừng chuỗi lệnh khi có lỗi: hãy sửa lỗi trước khi tiếp tục. Mở http://127.0.0.1:5412/; Ctrl+C dừng máy chủ cục bộ. Quá trình khởi tạo chỉ tạo `.env.local` nếu tệp chưa tồn tại. Cơ sở dữ liệu đang dùng là `var/populorum.sqlite`. Cookie của profiler yêu cầu HTTPS, kể cả khi kiểm thử thủ công trên máy cục bộ.

## Cập nhật

Dừng máy chủ và sao lưu cơ sở dữ liệu, các tệp và khóa trước khi thay mã nguồn. Giữ nguyên `.env.local`, `.guardian-shield/` và `var/`. Không bao giờ thay cơ sở dữ liệu đang dùng bằng `data/populorum-demo.sqlite`. Từ `apps/symfony`, chạy:

```bash
composer install --no-interaction --prefer-dist &&
php tools/init-demo.php &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo
```

Sau khi mọi bước đều thành công, khởi động lại máy chủ cục bộ:

```bash
php -S 127.0.0.1:5412 -t public
```

## Khắc phục sự cố

Nếu thiếu `vendor/autoload.php`, dừng máy chủ rồi chạy `composer install --no-interaction --prefer-dist` từ `apps/symfony`. Nếu thất bại, sửa phiên bản PHP hoặc phần mở rộng được báo lỗi trước khi chạy lại các lệnh Symfony. Không dùng `--ignore-platform-reqs`. Khi triển khai, DocumentRoot phải là `apps/symfony/public/`, dùng PHP-FPM, Apache hoặc Nginx và HTTPS; máy chủ tích hợp của PHP chỉ dành cho kiểm thử cục bộ.

## Tài liệu tham khảo (tiếng Pháp)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)

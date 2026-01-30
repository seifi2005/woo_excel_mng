# راهنمای نصب PhpSpreadsheet

## روش 1: استفاده از Composer (پیشنهادی)

اگر اتصال اینترنت دارید، دستور زیر را اجرا کنید:

```bash
cd wp-content/plugins/woo_excel_mng
composer install
```

## روش 2: نصب دستی

اگر composer کار نمی‌کند، می‌توانید PhpSpreadsheet را به صورت دستی نصب کنید:

### مرحله 1: دانلود PhpSpreadsheet

1. به آدرس زیر بروید:
   https://github.com/PHPOffice/PhpSpreadsheet/releases

2. آخرین نسخه را دانلود کنید (مثلاً `phpspreadsheet-1.29.x.zip`)

### مرحله 2: استخراج فایل

1. فایل دانلود شده را استخراج کنید
2. پوشه `vendor` را در پوشه افزونه (`woo_excel_mng`) ایجاد کنید
3. محتویات پوشه `src` از فایل استخراج شده را در `vendor/phpoffice/phpspreadsheet/src` کپی کنید

### مرحله 3: ایجاد Autoloader

یک فایل `vendor/autoload.php` ایجاد کنید با محتوای زیر:

```php
<?php
// Autoloader ساده برای PhpSpreadsheet
spl_autoload_register(function ($class) {
    $prefix = 'PhpOffice\\PhpSpreadsheet\\';
    $base_dir = __DIR__ . '/phpoffice/phpspreadsheet/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});
```

## روش 3: استفاده از CDN یا Mirror

اگر در ایران هستید و مشکل اتصال دارید، می‌توانید از mirror استفاده کنید:

```bash
composer config -g repo.packagist composer https://packagist.ir
composer install
```

یا:

```bash
composer config repo.packagist composer https://mirrors.aliyun.com/composer/
composer install
```

## بررسی نصب

پس از نصب، فایل زیر باید وجود داشته باشد:
- `vendor/phpoffice/phpspreadsheet/src/PhpSpreadsheet/Spreadsheet.php`

یا می‌توانید با اجرای دستور زیر بررسی کنید:

```bash
php -r "require 'vendor/autoload.php'; echo class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet') ? 'OK' : 'FAILED';"
```

## نکته مهم

اگر PhpSpreadsheet نصب نشود، افزونه همچنان کار می‌کند اما قابلیت آپلود Excel غیرفعال خواهد بود و پیام خطای مناسب نمایش داده می‌شود.


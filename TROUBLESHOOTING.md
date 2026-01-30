# راهنمای عیب‌یابی

## خطای "خطای غیرمنتظره" هنگام فعال‌سازی

### مراحل عیب‌یابی:

#### 1. بررسی لاگ خطاها

در فایل `wp-config.php` مطمئن شوید که این خطوط وجود دارند:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

سپس فایل `wp-content/debug.log` را بررسی کنید.

#### 2. بررسی وجود فایل‌ها

مطمئن شوید که تمام فایل‌های زیر وجود دارند:

```
woo_excel_mng/
├── woo-excel-mng.php
├── includes/
│   ├── class-database.php
│   ├── class-admin.php
│   ├── class-frontend.php
│   ├── class-products.php
│   ├── class-shipping.php
│   ├── class-formulas.php
│   ├── class-excel-parser.php
│   └── class-frontend-compatibility.php
```

#### 3. بررسی دسترسی‌ها

مطمئن شوید که:
- پوشه افزونه قابل خواندن است
- وردپرس دسترسی نوشتن در پایگاه داده دارد
- افزونه ووکامرس فعال است

#### 4. تست دستی

می‌توانید فایل `debug-activation.php` را در root وردپرس قرار داده و اجرا کنید:

```bash
php debug-activation.php
```

#### 5. بررسی نسخه PHP

افزونه نیاز به PHP 7.4 یا بالاتر دارد:

```bash
php -v
```

#### 6. غیرفعال کردن افزونه‌های دیگر

گاهی افزونه‌های دیگر باعث تداخل می‌شوند. به صورت موقت سایر افزونه‌ها را غیرفعال کنید.

#### 7. بررسی خطاهای رایج

**خطا: "Class not found"**
- مطمئن شوید که تمام فایل‌های کلاس وجود دارند
- بررسی کنید که autoloader به درستی کار می‌کند

**خطا: "Database error"**
- بررسی کنید که دسترسی به پایگاه داده وجود دارد
- بررسی کنید که جداول به درستی ایجاد شده‌اند

**خطا: "Memory limit exceeded"**
- در `php.ini` مقدار `memory_limit` را افزایش دهید

### گزارش خطا

اگر مشکل حل نشد، لطفاً اطلاعات زیر را ارسال کنید:

1. پیام خطای کامل
2. محتوای `wp-content/debug.log`
3. نسخه PHP: `php -v`
4. نسخه وردپرس
5. نسخه ووکامرس
6. لیست افزونه‌های فعال


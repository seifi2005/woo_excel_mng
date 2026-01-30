# راهنمای فعال کردن ZipArchive در XAMPP

## مشکل:
```
PHP Fatal error: Class "ZipArchive" not found
```

این خطا به این معنی است که افزونه `zip` در PHP فعال نیست.

## راه‌حل:

### روش 1: فعال کردن در php.ini

1. فایل `php.ini` را باز کنید:
   - مسیر: `C:\xampp\php\php.ini`

2. خط زیر را پیدا کنید:
   ```ini
   ;extension=zip
   ```

3. سمی‌کالن (;) را حذف کنید:
   ```ini
   extension=zip
   ```

4. فایل را ذخیره کنید

5. Apache را Restart کنید:
   - از XAMPP Control Panel
   - روی Stop کلیک کنید
   - سپس Start کنید

### روش 2: بررسی فعال بودن

برای بررسی اینکه ZipArchive فعال است یا نه:

1. یک فایل PHP ایجاد کنید با محتوای زیر:
   ```php
   <?php
   if (class_exists('ZipArchive')) {
       echo "✅ ZipArchive فعال است!";
   } else {
       echo "❌ ZipArchive فعال نیست!";
   }
   phpinfo();
   ?>
   ```

2. در مرورگر باز کنید

3. در صفحه phpinfo، دنبال "zip" بگردید

### روش 3: بررسی در Command Line

```bash
php -m | findstr zip
```

اگر `zip` را دیدید، یعنی فعال است.

## پس از فعال کردن:

1. Apache را Restart کنید
2. صفحه افزونه را Refresh کنید
3. دوباره فایل Excel را آپلود کنید

## نکته:

اگر بعد از فعال کردن هنوز خطا می‌دهد:
- مطمئن شوید که فایل `php.ini` درست را ویرایش کرده‌اید
- ممکن است چند فایل `php.ini` وجود داشته باشد
- برای پیدا کردن فایل درست:
  ```bash
  php --ini
  ```

## بررسی نسخه PHP:

```bash
php -v
```

ZipArchive از PHP 5.2.0 به بعد موجود است.

---

**پس از فعال کردن ZipArchive، افزونه باید به درستی کار کند!** ✅


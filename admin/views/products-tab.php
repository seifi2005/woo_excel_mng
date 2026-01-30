<?php
/**
 * تب مدیریت محصولات
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="woo-excel-mng-products">
    <div class="section-header">
        <h2><?php _e('مدیریت محصولات', 'woo-excel-mng'); ?></h2>
        <p class="description"><?php _e('آپلود فایل Excel برای ایجاد یا به‌روزرسانی محصولات متغیر', 'woo-excel-mng'); ?></p>
    </div>
    
    <div class="upload-section">
        <div class="upload-box">
            <h3><?php _e('آپلود فایل Excel محصولات', 'woo-excel-mng'); ?></h3>
            <p class="help-text">
                <?php _e('فرمت فایل باید شامل ستون‌های زیر باشد:', 'woo-excel-mng'); ?>
            </p>
            <ul class="excel-format-list">
                <li><strong><?php _e('محصول', 'woo-excel-mng'); ?></strong> - <?php _e('نام محصول اصلی', 'woo-excel-mng'); ?></li>
                <li><strong><?php _e('طول', 'woo-excel-mng'); ?></strong> - <?php _e('مقدار ویژگی طول', 'woo-excel-mng'); ?></li>
                <li><strong><?php _e('رنگ', 'woo-excel-mng'); ?></strong> - <?php _e('مقدار ویژگی رنگ', 'woo-excel-mng'); ?></li>
                <li><strong><?php _e('ضخامت', 'woo-excel-mng'); ?></strong> - <?php _e('مقدار ویژگی ضخامت', 'woo-excel-mng'); ?></li>
                <li><strong><?php _e('وزن (کیلوگرم)', 'woo-excel-mng'); ?></strong> - <?php _e('وزن محصول برای محاسبه حمل‌ونقل', 'woo-excel-mng'); ?></li>
                <li><strong><?php _e('قیمت پایه', 'woo-excel-mng'); ?></strong> - <?php _e('قیمت پایه محصول', 'woo-excel-mng'); ?></li>
            </ul>
            
            <form method="post" action="" enctype="multipart/form-data" class="upload-form">
                <?php wp_nonce_field('woo_excel_mng_upload_products', 'woo_excel_mng_nonce'); ?>
                <input type="hidden" name="action" value="upload_products">
                
                <div class="form-group">
                    <label for="products_file" class="file-label">
                        <span class="dashicons dashicons-upload"></span>
                        <span class="label-text"><?php _e('انتخاب فایل Excel', 'woo-excel-mng'); ?></span>
                        <input type="file" name="products_file" id="products_file" accept=".xlsx,.xls" required>
                    </label>
                    <span class="file-name" id="products_file_name"></span>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="button button-primary button-large">
                        <span class="dashicons dashicons-upload"></span>
                        <?php _e('آپلود و پردازش', 'woo-excel-mng'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="info-box">
        <h4><?php _e('نکات مهم:', 'woo-excel-mng'); ?></h4>
        <ul>
            <li><?php _e('اگر برای یک Variation قیمت تعریف نشده باشد، به صورت "ناموجود" تنظیم می‌شود.', 'woo-excel-mng'); ?></li>
            <li><?php _e('محصولات ایجاد شده در بخش "محصولات → همه محصولات" قابل مدیریت هستند.', 'woo-excel-mng'); ?></li>
            <li><?php _e('وزن برای محاسبه هزینه حمل‌ونقل استفاده می‌شود.', 'woo-excel-mng'); ?></li>
        </ul>
    </div>
</div>


<?php
/**
 * کلاس مدیریت محصولات
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_Excel_Mng_Products {
    
    /**
     * ایجاد یا به‌روزرسانی محصولات از داده‌های Excel
     */
    public static function import_products($products_data) {
        $created_count = 0;
        $updated_count = 0;
        $errors = array();
        
        // گروه‌بندی محصولات بر اساس نام
        $products_grouped = array();
        foreach ($products_data as $data) {
            $product_name = $data['product'];
            if (!isset($products_grouped[$product_name])) {
                $products_grouped[$product_name] = array();
            }
            $products_grouped[$product_name][] = $data;
        }
        
        foreach ($products_grouped as $product_name => $variations_data) {
            try {
                // یافتن یا ایجاد محصول اصلی
                $product_id = self::get_or_create_variable_product($product_name);
                
                if (is_wp_error($product_id)) {
                    $errors[] = sprintf(__('خطا در ایجاد محصول "%s": %s', 'woo-excel-mng'), $product_name, $product_id->get_error_message());
                    continue;
                }
                
                // جمع‌آوری تمام ویژگی‌ها
                $attributes = self::collect_attributes($variations_data);
                
                // تنظیم ویژگی‌های محصول
                self::set_product_attributes($product_id, $attributes);
                
                // دریافت مجدد محصول برای دسترسی به slug های attributes
                $parent_product = wc_get_product($product_id);
                if (!$parent_product) {
                    $errors[] = sprintf(__('خطا در دریافت محصول "%s"', 'woo-excel-mng'), $product_name);
                    continue;
                }
                
                // ایجاد یا به‌روزرسانی Variationها
                foreach ($variations_data as $variation_data) {
                    $result = self::create_or_update_variation($product_id, $variation_data, $attributes);
                    
                    if (is_wp_error($result)) {
                        $errors[] = sprintf(
                            __('خطا در ایجاد Variation برای "%s" (طول: %s, رنگ: %s, ضخامت: %s): %s', 'woo-excel-mng'),
                            $product_name,
                            $variation_data['length'],
                            $variation_data['color'],
                            $variation_data['thickness'],
                            $result->get_error_message()
                        );
                    } elseif ($result['created']) {
                        $created_count++;
                    } else {
                        $updated_count++;
                    }
                }
                
                // Sync محصول والد بعد از تمام Variationها (برای به‌روزرسانی min/max price)
                $parent_product = wc_get_product($product_id);
                if ($parent_product && $parent_product->is_type('variable')) {
                    // استفاده از متد استاندارد ووکامرس
                    $parent_product->variable_product_sync();
                    wc_delete_product_transients($product_id);
                }
                
            } catch (Exception $e) {
                $errors[] = sprintf(__('خطا در پردازش محصول "%s": %s', 'woo-excel-mng'), $product_name, $e->getMessage());
            }
        }
        
        return array(
            'success' => empty($errors),
            'created' => $created_count,
            'updated' => $updated_count,
            'errors' => $errors
        );
    }
    
    /**
     * یافتن یا ایجاد محصول متغیر
     */
    private static function get_or_create_variable_product($product_name) {
        // جستجوی محصول متغیر موجود
        $existing_variable = wc_get_products(array(
            'name' => $product_name,
            'type' => 'variable',
            'limit' => 1,
            'return' => 'ids'
        ));

        if (!empty($existing_variable)) {
            return $existing_variable[0];
        }

        // اگر محصولی با همین نام وجود دارد، آن را به متغیر تبدیل کن
        $existing_any = wc_get_products(array(
            'name' => $product_name,
            'limit' => 1,
            'return' => 'ids'
        ));

        if (!empty($existing_any)) {
            return self::ensure_variable_product($existing_any[0]);
        }
        
        // ایجاد محصول جدید
        $product = new WC_Product_Variable();
        $product->set_name($product_name);
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        $product->set_manage_stock(false);
        $product->save();
        
        return $product->get_id();
    }

    /**
     * اطمینان از اینکه محصول والد متغیر است
     */
    private static function ensure_variable_product($product_id) {
        $product = wc_get_product($product_id);

        if (!$product) {
            return new WP_Error('product_not_found', __('محصول والد یافت نشد.', 'woo-excel-mng'));
        }

        if ($product->is_type('variable')) {
            return $product_id;
        }

        if ($product->is_type('variation')) {
            return new WP_Error('invalid_product', __('محصول والد نامعتبر است.', 'woo-excel-mng'));
        }

        // تبدیل محصول ساده به متغیر
        wp_set_object_terms($product_id, 'variable', 'product_type', false);

        $product = wc_get_product($product_id);
        if ($product && $product->is_type('variable')) {
            return $product_id;
        }

        // تلاش مجدد با ساخت نمونه متغیر
        $variable_product = new WC_Product_Variable($product_id);
        $variable_product->save();

        $product = wc_get_product($product_id);
        if ($product && $product->is_type('variable')) {
            return $product_id;
        }

        return new WP_Error('invalid_product', __('محصول والد نامعتبر است.', 'woo-excel-mng'));
    }
    
    /**
     * جمع‌آوری ویژگی‌ها از داده‌ها
     */
    private static function collect_attributes($variations_data) {
        $attributes = array(
            'length' => array(),
            'color' => array(),
            'thickness' => array()
        );
        
        foreach ($variations_data as $data) {
            if (!empty($data['length'])) {
                $attributes['length'][$data['length']] = $data['length'];
            }
            if (!empty($data['color'])) {
                $attributes['color'][$data['color']] = $data['color'];
            }
            if (!empty($data['thickness'])) {
                $attributes['thickness'][$data['thickness']] = $data['thickness'];
            }
        }
        
        return $attributes;
    }
    
    /**
     * تنظیم ویژگی‌های محصول
     */
    private static function set_product_attributes($product_id, $attributes) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }
        
        $product_attributes = array();
        
        // ویژگی طول
        if (!empty($attributes['length'])) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_id(0);
            $attribute->set_name('طول');
            $attribute->set_options(array_values($attributes['length']));
            $attribute->set_visible(true);
            $attribute->set_variation(true);
            $product_attributes[] = $attribute;
        }
        
        // ویژگی رنگ
        if (!empty($attributes['color'])) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_id(0);
            $attribute->set_name('رنگ');
            $attribute->set_options(array_values($attributes['color']));
            $attribute->set_visible(true);
            $attribute->set_variation(true);
            $product_attributes[] = $attribute;
        }
        
        // ویژگی ضخامت
        if (!empty($attributes['thickness'])) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_id(0);
            $attribute->set_name('ضخامت');
            $attribute->set_options(array_values($attributes['thickness']));
            $attribute->set_visible(true);
            $attribute->set_variation(true);
            $product_attributes[] = $attribute;
        }
        
        $product->set_attributes($product_attributes);
        $product->save();
        
        // Clear cache
        wc_delete_product_transients($product_id);
    }
    
    /**
     * ایجاد یا به‌روزرسانی Variation
     * @deprecated استفاده از create_or_update_variation_fixed
     */
    private static function create_or_update_variation($product_id, $variation_data, $attributes) {
        return self::create_or_update_variation_fixed($product_id, $variation_data);
    }
    
    /**
     * ایجاد یا به‌روزرسانی Variation - نسخه اصلاح شده
     */
    private static function create_or_update_variation_fixed($product_id, $variation_data) {
        // دریافت محصول والد برای دسترسی به attributes
        $parent_product = wc_get_product($product_id);
        if (!$parent_product || !$parent_product->is_type('variable')) {
            $ensure_result = self::ensure_variable_product($product_id);
            if (is_wp_error($ensure_result)) {
                return $ensure_result;
            }
            $parent_product = wc_get_product($product_id);
        }

        if (!$parent_product || !$parent_product->is_type('variable')) {
            return new WP_Error('invalid_product', __('محصول والد نامعتبر است.', 'woo-excel-mng'));
        }
        
        // دریافت attributes محصول والد
        // در ووکامرس، برای custom attributes، slug از sanitize_title(name) ساخته می‌شود
        $parent_attributes = $parent_product->get_attributes();
        
        // ساخت map از نام فارسی به slug واقعی
        $name_to_slug = array();
        foreach ($parent_attributes as $attr_key => $attribute) {
            // برای custom attributes (نه taxonomy)
            if (!$attribute->is_taxonomy()) {
                $attr_name = $attribute->get_name();
                // کلید در get_attributes() همان slug است که از sanitize_title(name) ساخته شده
                $name_to_slug[$attr_name] = $attr_key;
            } else {
                // برای taxonomy attributes
                $name_to_slug[$attr_key] = $attr_key;
            }
        }
        
        // اگر map خالی است یا attribute پیدا نشد، از sanitize_title استفاده می‌کنیم
        // این برای custom attributes درست است
        if (!isset($name_to_slug['طول'])) {
            $name_to_slug['طول'] = sanitize_title('طول');
        }
        if (!isset($name_to_slug['رنگ'])) {
            $name_to_slug['رنگ'] = sanitize_title('رنگ');
        }
        if (!isset($name_to_slug['ضخامت'])) {
            $name_to_slug['ضخامت'] = sanitize_title('ضخامت');
        }
        
        // ساخت ویژگی‌ها با استفاده از slug (نه نام فارسی)
        $variation_attributes = array();
        
        if (!empty($variation_data['length'])) {
            $length_value = strval(trim($variation_data['length']));
            $length_slug = $name_to_slug['طول'];
            $variation_attributes[$length_slug] = $length_value;
        }
        if (!empty($variation_data['color'])) {
            $color_value = strval(trim($variation_data['color']));
            $color_slug = $name_to_slug['رنگ'];
            $variation_attributes[$color_slug] = $color_value;
        }
        if (!empty($variation_data['thickness'])) {
            $thickness_value = strval(trim($variation_data['thickness']));
            $thickness_slug = $name_to_slug['ضخامت'];
            $variation_attributes[$thickness_slug] = $thickness_value;
        }
        
        // بررسی اینکه آیا ویژگی‌ها خالی نیستند
        if (empty($variation_attributes)) {
            error_log('Woo Excel Mng ERROR: Variation attributes are empty! Data: ' . print_r($variation_data, true));
            return new WP_Error('empty_attributes', sprintf(
                __('ویژگی‌های Variation خالی است. طول: %s, رنگ: %s, ضخامت: %s', 'woo-excel-mng'),
                $variation_data['length'] ?? 'خالی',
                $variation_data['color'] ?? 'خالی',
                $variation_data['thickness'] ?? 'خالی'
            ));
        }
        
        // جستجوی Variation موجود (با استفاده از slug ها)
        $variation_id = self::find_variation_by_attributes($product_id, $variation_attributes);
        
        if ($variation_id) {
            // به‌روزرسانی Variation موجود
            $variation = wc_get_product($variation_id);
            $created = false;
        } else {
            // ایجاد Variation جدید
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product_id);
            $created = true;
        }
        
        // Debug: بررسی داده‌های ورودی
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Woo Excel Mng - Variation Data: ' . print_r($variation_data, true));
            error_log('Woo Excel Mng - Attributes (with slugs): ' . print_r($variation_attributes, true));
        }
        
        // تنظیم ویژگی‌ها با استفاده از slug ها
        $variation->set_attributes($variation_attributes);
        
        // تنظیم قیمت - دقیقاً همان قیمت Excel
        $base_price = isset($variation_data['base_price']) ? floatval($variation_data['base_price']) : 0;
        
        // تنظیم قیمت
        if ($base_price > 0) {
            $variation->set_regular_price($base_price);
            $variation->set_sale_price('');
            $variation->set_stock_status('instock');
        } else {
            $variation->set_regular_price('');
            $variation->set_stock_status('outofstock');
        }
        
        // تنظیم وزن
        if (isset($variation_data['weight']) && $variation_data['weight'] > 0) {
            $variation->set_weight(floatval($variation_data['weight']));
            $variation->update_meta_data('_woo_excel_weight', floatval($variation_data['weight']));
        }
        
        // تنظیم status
        $variation->set_status('publish');
        $variation->set_manage_stock(false);
        
        // ذخیره Variation
        $variation->save();
        
        $variation_id = $variation->get_id();
        
        // ذخیره مستقیم attributes در meta (برای اطمینان از ذخیره صحیح)
        foreach ($variation_attributes as $attr_slug => $attr_value) {
            // برای taxonomy attributes، از pa_ prefix استفاده می‌شود
            // برای custom attributes، از attribute_ prefix استفاده می‌شود
            if (strpos($attr_slug, 'pa_') === 0) {
                $meta_key = $attr_slug;
            } else {
                $meta_key = 'attribute_' . $attr_slug;
            }
            update_post_meta($variation_id, $meta_key, $attr_value);
        }
        
        // Debug: بررسی Variation بعد از save
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $saved_attrs = $variation->get_attributes();
            error_log('Woo Excel Mng - After Save - Variation ID: ' . $variation_id);
            error_log('Woo Excel Mng - After Save - Attributes: ' . print_r($saved_attrs, true));
            error_log('Woo Excel Mng - After Save - Price: ' . $variation->get_regular_price());
        }
        
        // ذخیره قیمت در meta (مطابق استاندارد ووکامرس)
        if ($base_price > 0) {
            $formatted_price = wc_format_decimal($base_price, 2);
            update_post_meta($variation_id, '_regular_price', $formatted_price);
            update_post_meta($variation_id, '_price', $formatted_price);
            update_post_meta($variation_id, '_sale_price', '');
            delete_post_meta($variation_id, '_sale_price_dates_from');
            delete_post_meta($variation_id, '_sale_price_dates_to');
        } else {
            update_post_meta($variation_id, '_regular_price', '');
            update_post_meta($variation_id, '_price', '');
            delete_post_meta($variation_id, '_sale_price');
        }
        
        // Clear تمام cacheها
        wc_delete_product_transients($variation_id);
        wc_delete_product_transients($product_id);
        clean_post_cache($variation_id);
        clean_post_cache($product_id);
        
        // Sync محصول والد (برای به‌روزرسانی min/max price) - بعد از تمام Variationها
        // این کار در انتهای import انجام می‌شود
        
        return array(
            'created' => $created,
            'variation_id' => $variation_id
        );
    }
    
    /**
     * یافتن Variation بر اساس ویژگی‌ها - نسخه اصلاح شده
     */
    private static function find_variation_by_attributes($product_id, $attributes) {
        $variations = wc_get_products(array(
            'type' => 'product_variation',
            'parent_id' => $product_id,
            'limit' => -1,
            'return' => 'ids'
        ));
        
        foreach ($variations as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation) {
                continue;
            }
            
            $variation_attrs = $variation->get_attributes();
            
            // مقایسه دقیق تمام ویژگی‌ها (تبدیل به string برای مقایسه)
            $match = true;
            foreach ($attributes as $key => $value) {
                $attr_value = isset($variation_attrs[$key]) ? strval(trim($variation_attrs[$key])) : '';
                $search_value = strval(trim($value));
                
                if ($attr_value !== $search_value) {
                    $match = false;
                    break;
                }
            }
            
            // بررسی تعداد ویژگی‌ها (باید یکسان باشد)
            if ($match && count($variation_attrs) !== count($attributes)) {
                $match = false;
            }
            
            if ($match) {
                return $variation_id;
            }
        }
        
        return false;
    }
    
    /**
     * به‌روزرسانی قیمت‌های Variationهای موجود
     * این تابع برای به‌روزرسانی Variationهایی که قبلاً ایجاد شده‌اند استفاده می‌شود
     */
    public static function update_existing_variation_prices($product_id, $products_data) {
        $updated = 0;
        $errors = array();
        
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) {
            return array('success' => false, 'message' => __('محصول یافت نشد یا متغیر نیست.', 'woo-excel-mng'));
        }
        
        foreach ($products_data as $data) {
            if ($product->get_name() !== $data['product']) {
                continue;
            }
            
            $variation_attributes = array(
                'طول' => $data['length'],
                'رنگ' => $data['color'],
                'ضخامت' => $data['thickness']
            );
            
            $variation_id = self::find_variation_by_attributes($product_id, $variation_attributes);
            
            if ($variation_id) {
                $variation = wc_get_product($variation_id);
                if ($variation) {
                    $base_price = floatval($data['base_price']);
                    
                    if ($base_price > 0) {
                        $variation->set_regular_price($base_price);
                        $variation->set_stock_status('instock');
                        update_post_meta($variation_id, '_regular_price', $base_price);
                        update_post_meta($variation_id, '_price', $base_price);
                    } else {
                        $variation->set_regular_price('');
                        $variation->set_stock_status('outofstock');
                        update_post_meta($variation_id, '_regular_price', '');
                        update_post_meta($variation_id, '_price', '');
                    }
                    
                    $variation->save();
                    wc_delete_product_transients($variation_id);
                    $updated++;
                }
            }
        }
        
        // Sync محصول والد
        $product->variable_product_sync();
        wc_delete_product_transients($product_id);
        
        return array(
            'success' => true,
            'updated' => $updated
        );
    }
}

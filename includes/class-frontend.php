<?php

/**
 * کلاس مدیریت Front-end
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_Excel_Mng_Frontend
{

    const CART_ITEM_METERAGE_KEY = 'woo_excel_meterage';
    const METERAGE_MIN_DEFAULT = 0.5;
    const METERAGE_STEP_DEFAULT = 0.5;
    private static $skip_cart_id_filter = false;

    /**
     * سازنده
     */
    public function __construct()
    {
        // تغییر label فیلد quantity به متراژ برای محصولات با فرمول
        add_filter('woocommerce_product_single_add_to_cart_text', array($this, 'change_add_to_cart_text'), 10, 1);
        add_filter('woocommerce_product_add_to_cart_text', array($this, 'change_add_to_cart_text'), 10, 1);

        // تغییر label quantity در صفحه محصول
        add_filter('woocommerce_quantity_input_args', array($this, 'change_quantity_label'), 10, 2);
        add_filter('woocommerce_quantity_input', array($this, 'render_custom_quantity_input'), 10, 3);

        // جلوگیری از ایجاد آیتم تکراری بر اساس متراژ
        add_filter('woocommerce_cart_id', array($this, 'filter_cart_id'), 10, 5);
        add_action('woocommerce_add_to_cart', array($this, 'merge_meterage_on_add_to_cart'), 10, 6);

        // تغییر quantity input در سبد خرید برای محصولات با فرمول (فقط Cart کلاسیک)
        add_filter('woocommerce_quantity_input_args', array($this, 'change_cart_quantity_input'), 10, 2);

        // مدل جدید: quantity همیشه 1، متراژ در cart item meta ذخیره می‌شود
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_meterage_cart_item_data'), 10, 3);
        add_filter('woocommerce_get_cart_item_from_session', array($this, 'restore_meterage_cart_item_data'), 10, 3);
        add_filter('woocommerce_add_to_cart_quantity', array($this, 'force_quantity_one_for_formula'), 10, 2);

        // رندر ورودی متراژ به جای quantity در Cart کلاسیک
        add_filter('woocommerce_cart_item_quantity', array($this, 'render_meterage_input_in_cart'), 10, 3);

        // پردازش آپدیت متراژ از فرم Cart کلاسیک
        add_action('woocommerce_update_cart_action_cart_updated', array($this, 'handle_meterage_update_from_post'), 5, 1);

        // اگر صفحه Cart با Blocks ساخته شده باشد، به Cart کلاسیک برگردان تا خطای integer حذف شود
        add_filter('the_content', array($this, 'force_classic_cart_for_blocks'), 1);

        // حفظ quantity اعشاری قبل از validation
        // توجه: woocommerce_stock_amount فقط یک پارامتر دارد و نمی‌تواند product را تشخیص دهد
        // بنابراین از hook های دیگر برای حفظ مقدار اعشاری استفاده می‌کنیم

        // فیلتر برای WooCommerce Blocks REST API
        add_filter('woocommerce_rest_cart_item_quantity', array($this, 'rest_cart_item_quantity'), 10, 3);
        add_filter('woocommerce_rest_cart_item_data', array($this, 'rest_cart_item_data'), 10, 2);

        // ارسال flag فرمول به JS در صفحه محصول
        add_filter('woocommerce_available_variation', array($this, 'add_variation_formula_flag'), 10, 3);

        // نمایش متراژ در سبد خرید (از quantity استفاده می‌شود)
        add_filter('woocommerce_cart_item_name', array($this, 'display_meterage_in_cart'), 10, 3);

        // محاسبه قیمت و وزن بر اساس متراژ
        // استفاده از priority بالا برای اجرا قبل از سایر افزونه‌ها
        add_action('woocommerce_before_calculate_totals', array($this, 'calculate_cart_totals'), 5, 1);

        // نمایش قیمت محاسبه شده در سبد خرید
        add_filter('woocommerce_cart_item_price', array($this, 'display_calculated_price'), 10, 3);

        // نمایش قیمت کل (subtotal) هر آیتم در سبد خرید
        add_filter('woocommerce_cart_item_subtotal', array($this, 'display_calculated_subtotal'), 10, 3);

        // بلاک حمل رایگان قدیمی حذف شد - حالا در display_shipping_info_box نمایش داده می‌شود

        // حذف فیلدهای پیش‌فرض و نمایش فیلدهای مورد نیاز
        add_filter('woocommerce_checkout_fields', array($this, 'customize_checkout_fields'), 20, 1);
        add_action('wp', array($this, 'reposition_checkout_billing_fields'));
        add_action('woocommerce_checkout_process', array($this, 'validate_destination_city'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_destination_city'));

        // محاسبه هزینه حمل‌ونقل
        add_filter('woocommerce_package_rates', array($this, 'calculate_shipping_rates'), 10, 2);

        // اضافه کردن هزینه حمل به فاکتور
        // استفاده از priority پایین برای اجرا بعد از سایر محاسبات
        add_action('woocommerce_cart_calculate_fees', array($this, 'add_shipping_fee_to_cart'), 20, 1);

        // همچنین در hook قبل از نمایش totals
        add_action('woocommerce_before_cart_totals', array($this, 'ensure_shipping_fee_calculated'), 5);

        // AJAX handlers
        add_action('wp_ajax_woo_excel_mng_calculate_price', array($this, 'ajax_calculate_price'));
        add_action('wp_ajax_nopriv_woo_excel_mng_calculate_price', array($this, 'ajax_calculate_price'));
        add_action('wp_ajax_woo_excel_mng_update_cart_item', array($this, 'ajax_update_cart_item'));
        add_action('wp_ajax_nopriv_woo_excel_mng_update_cart_item', array($this, 'ajax_update_cart_item'));
        add_action('wp_ajax_woo_excel_mng_save_destination_city', array($this, 'ajax_save_destination_city'));
        add_action('wp_ajax_nopriv_woo_excel_mng_save_destination_city', array($this, 'ajax_save_destination_city'));

        // تغییر label quantity در صفحه محصول برای محصولات با فرمول
        add_action('woocommerce_before_add_to_cart_quantity', array($this, 'add_quantity_label'), 10);

        // نمایش جمع وزن قبل از جمع کل سبد خرید
        add_action('woocommerce_cart_totals_before_order_total', array($this, 'render_total_weight_row'), 10);

        // اضافه کردن script برای تنظیم step و min در cart
        add_action('wp_footer', array($this, 'add_cart_quantity_script'), 99);

        // بارگذاری اسکریپت‌ها و استایل‌ها
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // غیرفعال کردن کد تخفیف در سبد خرید
        add_filter('woocommerce_coupons_enabled', array($this, 'disable_cart_coupons'), 10, 1);

        // نمایش باکس حمل‌ونقل در سبد خرید و تسویه حساب
        add_action('woocommerce_after_cart_table', array($this, 'display_shipping_info_box'), 10);
        add_action('woocommerce_checkout_after_order_review', array($this, 'display_shipping_info_box'), 20);

        // مخفی کردن حمل‌ونقل در سبد خرید
        add_filter('woocommerce_cart_needs_shipping', array($this, 'disable_cart_shipping_display'), 20, 1);
        add_filter('woocommerce_cart_totals_needs_shipping', array($this, 'disable_cart_shipping_display'), 20, 1);
    }

    /**
     * بارگذاری فایل‌های CSS و JS
     */
    public function enqueue_frontend_assets()
    {
        // فقط در صفحات محصول و سبد خرید
        if (!is_product() && !is_cart() && !is_checkout()) {
            return;
        }

        $has_formula_product = false;
        if (function_exists('is_product') && is_product() && function_exists('wc_get_product')) {
            $product = wc_get_product(get_the_ID());
            if ($product instanceof WC_Product_Variation) {
                $parent_id = $product->get_parent_id();
                $has_formula_product = (bool) Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
            } elseif ($product instanceof WC_Product) {
                $has_formula_product = (bool) Woo_Excel_Mng_Formulas::get_product_formula($product->get_id());
            }
        }

        wp_enqueue_style(
            'woo-excel-mng-frontend',
            WOO_EXCEL_MNG_PLUGIN_URL . 'frontend/assets/css/frontend.css',
            array(),
            WOO_EXCEL_MNG_VERSION
        );

        wp_enqueue_script(
            'woo-excel-mng-frontend',
            WOO_EXCEL_MNG_PLUGIN_URL . 'frontend/assets/js/frontend.js',
            array('jquery'),
            WOO_EXCEL_MNG_VERSION,
            true
        );

        wp_localize_script('woo-excel-mng-frontend', 'wooExcelMngFrontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('woo_excel_mng_frontend_nonce'),
            'has_formula_product' => $has_formula_product,
            'meterage_min' => $this->get_meterage_min(),
            'meterage_step' => $this->get_meterage_step(),
            'strings' => array(
                'enter_meterage' => __('لطفاً متراژ را وارد کنید.', 'woo-excel-mng'),
                'calculating' => __('در حال محاسبه...', 'woo-excel-mng'),
            )
        ));
    }

    /**
     * آیا این محصول/وارییشن دارای فرمول است؟
     */
    private function is_formula_product($product)
    {
        if (!$product) {
            return false;
        }

        if ($product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            return (bool) Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
        }

        if ($product->is_type('variable')) {
            return (bool) Woo_Excel_Mng_Formulas::get_product_formula($product->get_id());
        }

        // برای سایر نوع‌ها، اگر فرمول تعریف شده باشد true است
        return (bool) Woo_Excel_Mng_Formulas::get_product_formula($product->get_id());
    }

    /**
     * نرمال‌سازی ورودی اعشاری (پشتیبانی از ارقام فارسی/عربی)
     */
    private function normalize_decimal_input($value)
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        $value = str_replace(
            array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'),
            array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'),
            $value
        );

        // حذف جداکننده هزارگان و فاصله‌ها
        $value = str_replace(array('٬', ' '), '', $value);
        // نرمال‌سازی جداکننده اعشار
        $value = str_replace(array('٫', ','), '.', $value);

        return $value;
    }

    /**
     * حداقل متراژ مجاز
     */
    private function get_meterage_min()
    {
        $min = apply_filters('woo_excel_mng_meterage_min', self::METERAGE_MIN_DEFAULT);
        return max(0, floatval($min));
    }

    /**
     * گام افزایش متراژ
     */
    private function get_meterage_step()
    {
        $step = apply_filters('woo_excel_mng_meterage_step', self::METERAGE_STEP_DEFAULT);
        return max(0, floatval($step));
    }

    /**
     * نرمال‌سازی متراژ بر اساس گام
     */
    private function normalize_meterage_value($value)
    {
        $meterage = floatval($value);
        $step = $this->get_meterage_step();

        if ($step > 0) {
            $meterage = round($meterage / $step) * $step;
        }

        return round($meterage, 2);
    }

    /**
     * دریافت گزینه‌های شهر مقصد
     */
    private function get_destination_city_options() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'woo_excel_shipping_routes';
        $cities = $wpdb->get_col("SELECT DISTINCT destination_city FROM $table_name WHERE is_active = 1 ORDER BY destination_city");

        $options = array('' => __('-- انتخاب شهر --', 'woo-excel-mng'));
        if (!empty($cities)) {
            foreach ($cities as $city) {
                $options[$city] = $city;
            }
        }

        return $options;
    }

    /**
     * بیشترین متراژ در سبد خرید
     */
    private function get_cart_max_meterage($cart_items)
    {
        $max_meterage = 0;

        foreach ($cart_items as $cart_item) {
            $meterage = 0;
            if (isset($cart_item[self::CART_ITEM_METERAGE_KEY])) {
                $meterage = floatval($cart_item[self::CART_ITEM_METERAGE_KEY]);
            } elseif (isset($cart_item['quantity'])) {
                $meterage = floatval($cart_item['quantity']);
            }

            if ($meterage > $max_meterage) {
                $max_meterage = $meterage;
            }
        }

        return $max_meterage;
    }

    /**
     * محاسبه کلید سبد خرید بدون در نظر گرفتن متراژ
     */
    public function filter_cart_id($cart_id, $product_id, $variation_id, $variation, $cart_item_data)
    {
        if (self::$skip_cart_id_filter) {
            return $cart_id;
        }

        if (!isset($cart_item_data[self::CART_ITEM_METERAGE_KEY])) {
            return $cart_id;
        }

        $data = $cart_item_data;
        unset($data[self::CART_ITEM_METERAGE_KEY]);
        unset($data['woo_excel_unique']);

        if (function_exists('WC') && WC()->cart) {
            try {
                self::$skip_cart_id_filter = true;
                $new_id = WC()->cart->generate_cart_id($product_id, $variation_id, $variation, $data);
                return $new_id;
            } finally {
                self::$skip_cart_id_filter = false;
            }
        }

        return $cart_id;
    }

    /**
     * ادغام متراژ هنگام افزودن به سبد خرید
     */
    public function merge_meterage_on_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
    {
        if (!$variation_id) {
            return;
        }

        $variation_product = wc_get_product($variation_id);
        if (!$variation_product || !$this->is_formula_product($variation_product)) {
            return;
        }

        $incoming_meterage = 0;
        if (isset($cart_item_data[self::CART_ITEM_METERAGE_KEY])) {
            $incoming_meterage = $this->normalize_meterage_value($cart_item_data[self::CART_ITEM_METERAGE_KEY]);
        } elseif (isset($_REQUEST[self::CART_ITEM_METERAGE_KEY])) {
            $incoming_meterage = $this->normalize_meterage_value($this->normalize_decimal_input($_REQUEST[self::CART_ITEM_METERAGE_KEY]));
        } elseif (isset($_REQUEST['meterage'])) {
            $incoming_meterage = $this->normalize_meterage_value($this->normalize_decimal_input($_REQUEST['meterage']));
        } elseif (isset($_REQUEST['quantity'])) {
            $incoming_meterage = $this->normalize_meterage_value($this->normalize_decimal_input($_REQUEST['quantity']));
        }

        if ($incoming_meterage <= 0 || $incoming_meterage < $this->get_meterage_min()) {
            return;
        }

        $cart = WC()->cart;
        $cart_item = $cart ? $cart->get_cart_item($cart_item_key) : null;
        if (!$cart_item) {
            return;
        }

        $existing_meterage = isset($cart_item[self::CART_ITEM_METERAGE_KEY])
            ? floatval($cart_item[self::CART_ITEM_METERAGE_KEY])
            : 0;

        $new_meterage = $existing_meterage > 0
            ? $existing_meterage + $incoming_meterage
            : $incoming_meterage;

        $cart->cart_contents[$cart_item_key][self::CART_ITEM_METERAGE_KEY] = $new_meterage;
        $cart->cart_contents[$cart_item_key]['quantity'] = 1;
    }

    /**
     * افزودن flag فرمول به داده‌های variation برای JS
     */
    public function add_variation_formula_flag($variation_data, $product, $variation)
    {
        try {
            $variation_data['woo_excel_has_formula'] = $this->is_formula_product($variation);
        } catch (\Throwable $e) {
            $variation_data['woo_excel_has_formula'] = false;
        }

        return $variation_data;
    }

    /**
     * ذخیره متراژ در cart item data هنگام add to cart
     * متراژ از request (woo_excel_meterage) یا fallback از quantity گرفته می‌شود.
     */
    public function add_meterage_cart_item_data($cart_item_data, $product_id, $variation_id)
    {
        if ($variation_id <= 0) {
            return $cart_item_data;
        }

        $variation = wc_get_product($variation_id);
        if (!$variation || !$variation->is_type('variation')) {
            return $cart_item_data;
        }

        if (!$this->is_formula_product($variation)) {
            return $cart_item_data;
        }

        $meterage = null;
        $meterage_raw = null;

        if (isset($_REQUEST[self::CART_ITEM_METERAGE_KEY])) {
            $meterage_raw = $_REQUEST[self::CART_ITEM_METERAGE_KEY];
        } elseif (isset($_REQUEST['meterage'])) {
            $meterage_raw = $_REQUEST['meterage'];
        } elseif (isset($_REQUEST['quantity'])) {
            $meterage_raw = $_REQUEST['quantity'];
        }

        if ($meterage_raw !== null) {
            $meterage_raw = $this->normalize_decimal_input($meterage_raw);
            $meterage = $this->normalize_meterage_value($meterage_raw);
        }

        if ($meterage !== null && $meterage >= $this->get_meterage_min()) {
            $cart_item_data[self::CART_ITEM_METERAGE_KEY] = $meterage;
        }

        return $cart_item_data;
    }

    /**
     * بازیابی متراژ از session
     */
    public function restore_meterage_cart_item_data($cart_item, $values, $cart_item_key)
    {
        if (isset($values[self::CART_ITEM_METERAGE_KEY])) {
            $cart_item[self::CART_ITEM_METERAGE_KEY] = floatval($values[self::CART_ITEM_METERAGE_KEY]);
        }

        return $cart_item;
    }

    /**
     * برای محصولات دارای فرمول quantity را همیشه 1 می‌کنیم (سازگار با Woo/Blocks)
     */
    public function force_quantity_one_for_formula($quantity, $product_id)
    {
        $product = wc_get_product($product_id);
        if (!$product) {
            return $quantity;
        }

        if ($this->is_formula_product($product)) {
            return 1;
        }

        return $quantity;
    }

    /**
     * رندر ورودی متراژ در cart کلاسیک (به جای quantity)
     * و همزمان qty واقعی را به صورت hidden روی 1 نگه می‌دارد.
     */
    public function render_meterage_input_in_cart($product_quantity, $cart_item_key, $cart_item)
    {
        $product = isset($cart_item['data']) ? $cart_item['data'] : null;
        if (!$product || !$product->is_type('variation') || !$this->is_formula_product($product)) {
            return $product_quantity;
        }

        $meterage = isset($cart_item[self::CART_ITEM_METERAGE_KEY]) ? floatval($cart_item[self::CART_ITEM_METERAGE_KEY]) : $this->get_meterage_min();
        if ($meterage < $this->get_meterage_min()) {
            $meterage = $this->get_meterage_min();
        }

        $meterage_formatted = woo_excel_mng_format_number($meterage, 2, '.', '');

        $html  = '<div class="woo-excel-meterage-qty">';
        $html .= '<label class="screen-reader-text" for="woo-excel-meterage-' . esc_attr($cart_item_key) . '">' . esc_html__('متراژ (متر)', 'woo-excel-mng') . '</label>';
        $html .= '<input type="text" class="input-text qty text woo-excel-meterage-input" ';
        $html .= 'name="' . esc_attr(self::CART_ITEM_METERAGE_KEY) . '[' . esc_attr($cart_item_key) . ']" ';
        $html .= 'id="woo-excel-meterage-' . esc_attr($cart_item_key) . '" ';
        $html .= 'value="' . esc_attr($meterage_formatted) . '" data-step="' . esc_attr($this->get_meterage_step()) . '" data-min="' . esc_attr($this->get_meterage_min()) . '" inputmode="decimal" />';
        $html .= '<input type="hidden" name="cart[' . esc_attr($cart_item_key) . '][qty]" value="1" />';
        $html .= '</div>';

        return $html;
    }

    /**
     * پردازش متراژ از فرم Cart کلاسیک
     */
    public function handle_meterage_update_from_post($cart_updated)
    {
        if (!function_exists('is_cart') || !is_cart()) {
            return $cart_updated;
        }

        if (!isset($_POST[self::CART_ITEM_METERAGE_KEY]) || !is_array($_POST[self::CART_ITEM_METERAGE_KEY])) {
            return $cart_updated;
        }

        $cart = WC()->cart;
        if (!$cart) {
            return $cart_updated;
        }

        foreach ($_POST[self::CART_ITEM_METERAGE_KEY] as $cart_item_key => $meterage_raw) {
            $cart_item_key = sanitize_text_field($cart_item_key);
            $meterage_raw = $this->normalize_decimal_input($meterage_raw);
            $meterage = $this->normalize_meterage_value($meterage_raw);

            if ($meterage < $this->get_meterage_min()) {
                continue;
            }

            $cart_item = $cart->get_cart_item($cart_item_key);
            if (!$cart_item) {
                continue;
            }

            $product = isset($cart_item['data']) ? $cart_item['data'] : null;
            if (!$product || !$product->is_type('variation') || !$this->is_formula_product($product)) {
                continue;
            }

            $cart->cart_contents[$cart_item_key][self::CART_ITEM_METERAGE_KEY] = $meterage;
            $cart->cart_contents[$cart_item_key]['quantity'] = 1;
        }

        return $cart_updated;
    }

    /**
     * اگر Cart با Blocks ساخته شده باشد، آن را به Cart کلاسیک تبدیل کن.
     */
    public function force_classic_cart_for_blocks($content)
    {
        if (!function_exists('is_cart') || !is_cart()) {
            return $content;
        }

        if (function_exists('has_block') && has_block('woocommerce/cart', $content)) {
            return do_shortcode('[woocommerce_cart]');
        }

        return $content;
    }

    /**
     * تغییر متن دکمه افزودن به سبد
     */
    public function change_add_to_cart_text($text)
    {
        global $product;
        if ($product && $product->is_type('variable')) {
            $formula = Woo_Excel_Mng_Formulas::get_product_formula($product->get_id());
            if ($formula) {
                return __('افزودن به سبد خرید', 'woo-excel-mng');
            }
        }
        return $text;
    }

    /**
     * تغییر label فیلد quantity به متراژ
     */
    public function change_quantity_label($args, $product)
    {
        $target_product_id = 0;

        if ($product instanceof WC_Product_Variation) {
            $target_product_id = $product->get_parent_id();
        } elseif ($product instanceof WC_Product) {
            $target_product_id = $product->get_id();
        } elseif (function_exists('is_product') && is_product()) {
            $target_product_id = get_the_ID();
        }

        $has_formula = false;
        if ($target_product_id) {
            $has_formula = (bool) Woo_Excel_Mng_Formulas::get_product_formula($target_product_id);
        }

        if ($has_formula) {
            $args['input_name'] = 'quantity';
            $args['min_value'] = $this->get_meterage_min();
            $args['step'] = $this->get_meterage_step();
            $args['inputmode'] = 'decimal';
            // اضافه کردن label سفارشی
            if (!isset($args['classes'])) {
                $args['classes'] = array();
            }
            $args['classes'][] = 'woo-excel-meterage-quantity';
        }
        return $args;
    }

    /**
     * رندر ورودی سفارشی متراژ برای محصولات دارای فرمول
     */
    public function render_custom_quantity_input($html, $product = null, $args = array())
    {
        if (!function_exists('is_product') || !is_product()) {
            return $html;
        }

        if (!is_array($args)) {
            $args = array();
        }

        $product_id = 0;
        if ($product instanceof WC_Product_Variation) {
            $product_id = $product->get_parent_id();
        } elseif ($product instanceof WC_Product) {
            $product_id = $product->get_id();
        } else {
            $product_id = get_the_ID();
        }

        if (!$product_id || !Woo_Excel_Mng_Formulas::get_product_formula($product_id)) {
            return $html;
        }

        $input_id = isset($args['input_id']) ? $args['input_id'] : 'woo_excel_meterage';
        $input_value = isset($args['input_value']) ? $args['input_value'] : 1;
        $input_value = $input_value ? $input_value : 1;
        $min_value = $this->get_meterage_min();
        $step_value = $this->get_meterage_step();

        $label = esc_html__('متراژ (متر)', 'woo-excel-mng');
        $html  = '<div class="quantity">';
        $html .= '<label class="screen-reader-text" for="' . esc_attr($input_id) . '">' . $label . '</label>';
        $html .= '<input type="text" id="' . esc_attr($input_id) . '" class="input-text qty text woo-excel-meterage-quantity" ';
        $html .= 'name="woo_excel_meterage" value="' . esc_attr($input_value) . '" ';
        $html .= 'inputmode="decimal" autocomplete="off" data-min="' . esc_attr($min_value) . '" data-step="' . esc_attr($step_value) . '" />';
        $html .= '<input type="hidden" name="quantity" value="1" />';
        $html .= '</div>';

        return $html;
    }

    /**
     * تغییر quantity input در سبد خرید برای محصولات با فرمول
     */
    public function change_cart_quantity_input($args, $product)
    {
        // بررسی اینکه آیا در سبد خرید هستیم
        if (!is_cart()) {
            return $args;
        }

        // اگر product null است، از cart item استفاده می‌کنیم
        if (!$product) {
            // از context استفاده می‌کنیم
            global $woocommerce;
            if (isset($woocommerce->cart)) {
                // بررسی تمام cart items
                foreach ($woocommerce->cart->get_cart() as $cart_item_key => $cart_item) {
                    $item_product = $cart_item['data'];
                    if ($item_product && $item_product->is_type('variation')) {
                        $parent_id = $item_product->get_parent_id();
                        $formula = Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
                        if ($formula) {
                            // تنظیم step و min برای quantity در cart
                            $args['min_value'] = $this->get_meterage_min();
                            $args['step'] = $this->get_meterage_step();
                            break;
                        }
                    }
                }
            }
        } elseif ($product && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $formula = Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
            if ($formula) {
                $args['min_value'] = $this->get_meterage_min();
                $args['step'] = $this->get_meterage_step();
            }
        }

        return $args;
    }

    /**
     * نمایش متراژ در سبد خرید (از quantity استفاده می‌شود)
     */
    public function display_meterage_in_cart($product_name, $cart_item, $cart_item_key)
    {
        $product = $cart_item['data'];
        if ($product && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $formula = Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
            if ($formula) {
                $meterage = isset($cart_item[self::CART_ITEM_METERAGE_KEY]) ? floatval($cart_item[self::CART_ITEM_METERAGE_KEY]) : $this->get_meterage_min();
                $formatted_meterage = woo_excel_mng_format_number($meterage, 2, '.', '');
                $product_name .= '<br><small class="woo-excel-meterage-display">' . sprintf(__('متراژ: %s متر', 'woo-excel-mng'), $formatted_meterage) . '</small>';
            }
        }

        return $product_name;
    }

    /**
     * نمایش جمع وزن زیر فاکتور
     */
    public function render_total_weight_row()
    {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        $total_weight = 0;
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (isset($cart_item['woo_excel_calculated_weight'])) {
                $total_weight += floatval($cart_item['woo_excel_calculated_weight']);
                continue;
            }

            $product = isset($cart_item['data']) ? $cart_item['data'] : null;
            if (!$product) {
                continue;
            }

            $meterage = isset($cart_item[self::CART_ITEM_METERAGE_KEY])
                ? floatval($cart_item[self::CART_ITEM_METERAGE_KEY])
                : (isset($cart_item['quantity']) ? floatval($cart_item['quantity']) : 1);

            $product_weight = floatval($product->get_weight());
            if ($product_weight > 0) {
                $total_weight += $product_weight * $meterage;
            }
        }

        echo '<tr class="woo-excel-total-weight">';
        echo '<th>' . esc_html__('جمع وزن', 'woo-excel-mng') . '</th>';
        echo '<td data-title="' . esc_attr__('جمع وزن', 'woo-excel-mng') . '">' . wc_format_weight($total_weight) . '</td>';
        echo '</tr>';
    }

    /**
     * نمایش بلاک اطلاعات حمل‌ونقل برای کل سبد خرید
     * شامل: وزن هر آیتم، جمع کل وزن، انتخاب شهر، هزینه حمل، نوع وسیله، بررسی حمل رایگان
     */
    public function display_shipping_info_box()
    {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        $is_cart = function_exists('is_cart') && is_cart();
        $is_checkout = function_exists('is_checkout') && is_checkout();

        if ($is_cart) {
            echo '<div class="woo-excel-shipping-info-box woo-excel-shipping-minimal">';
            echo '<h3>' . esc_html__('اطلاعات حمل‌ونقل', 'woo-excel-mng') . '</h3>';
            echo '<div class="woo-excel-shipping-note">';
            echo '<span class="dashicons dashicons-info"></span>';
            echo '<p>' . esc_html__('هزینه حمل در مرحله بعد محاسبه می‌شود.', 'woo-excel-mng') . '</p>';
            echo '</div>';
            echo '</div>';
            return;
        }

        if (!$is_checkout) {
            return;
        }

        // دریافت شهر مبدا
        $origin_city = get_option('woo_excel_mng_origin_city', 'تهران');

        // دریافت تنظیمات Premium
        $premium_threshold = floatval(get_option('woo_excel_mng_premium_threshold', 65000000));
        $shipping_percentage = floatval(get_option('woo_excel_mng_shipping_percentage', 2)) / 100; // تبدیل به اعشار

        // دریافت شهر مقصد از session
        $destination_city = WC()->session->get('woo_excel_destination_city', '');

        // دریافت لیست شهرهای موجود
        $city_options = $this->get_destination_city_options();
        if (count($city_options) <= 1) {
            return;
        }

        // محاسبه وزن هر آیتم و جمع کل
        $total_weight = 0;
        $cart_total = 0;

        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $product_name = $product->get_name();

            // دریافت متراژ
            $meterage = isset($cart_item[self::CART_ITEM_METERAGE_KEY])
                ? floatval($cart_item[self::CART_ITEM_METERAGE_KEY])
                : (isset($cart_item['quantity']) ? floatval($cart_item['quantity']) : 1);

            // دریافت attributes از variation
            $length = '';
            $color = '';
            $thickness = '';

            if ($product->is_type('variation')) {
                $attributes = $product->get_attributes();

                // طول
                $length_slug = sanitize_title('طول');
                if (isset($attributes[$length_slug])) {
                    $length = $attributes[$length_slug];
                } elseif (isset($attributes['pa_' . $length_slug])) {
                    $length = $attributes['pa_' . $length_slug];
                }

                // رنگ
                $color_slug = sanitize_title('رنگ');
                if (isset($attributes[$color_slug])) {
                    $color = $attributes[$color_slug];
                } elseif (isset($attributes['pa_' . $color_slug])) {
                    $color = $attributes['pa_' . $color_slug];
                }

                // ضخامت
                $thickness_slug = sanitize_title('ضخامت');
                if (isset($attributes[$thickness_slug])) {
                    $thickness = $attributes[$thickness_slug];
                } elseif (isset($attributes['pa_' . $thickness_slug])) {
                    $thickness = $attributes['pa_' . $thickness_slug];
                }
            }

            // محاسبه وزن این آیتم
            $item_weight = 0;
            if (isset($cart_item['woo_excel_calculated_weight'])) {
                $item_weight = floatval($cart_item['woo_excel_calculated_weight']);
            } else {
                $item_weight = floatval($product->get_weight()) * $meterage;
            }

            $total_weight += $item_weight;

            // محاسبه قیمت این آیتم
            if (isset($cart_item['woo_excel_calculated_price'])) {
                $cart_total += floatval($cart_item['woo_excel_calculated_price']);
            } else {
                $item_price = floatval($product->get_price());
                $quantity = isset($cart_item['quantity']) ? floatval($cart_item['quantity']) : 1;
                $cart_total += $item_price * $quantity;
            }
        }

        $max_meterage = $this->get_cart_max_meterage(WC()->cart->get_cart());

        // محاسبه هزینه حمل و نوع وسیله (اگر شهر انتخاب شده باشد)
        $shipping_cost = 0;
        $vehicle = '';
        $vehicle_name = '';
        $is_free_shipping = false;
        $is_premium_mode = ($cart_total >= $premium_threshold);
        $target_amount = 0;
        $shipping_percentage_amount = 0;
        $base_shipping_cost = 0;
        $vehicle_upgrade_notice = '';

        if ($destination_city && $total_weight > 0) {
            // محاسبه هزینه حمل از جدول
            $shipping_result = Woo_Excel_Mng_Shipping::calculate_shipping_cost(
                $origin_city,
                $destination_city,
                $total_weight,
                $max_meterage
            );

            if ($shipping_result) {
                $base_shipping_cost = floatval($shipping_result['cost']);
                $vehicle = $shipping_result['vehicle'];

                $vehicle_names = array(
                    'peykan' => 'پیکان',
                    'mazda' => 'مزدا',
                    'nissan' => 'نیسان'
                );
                $vehicle_name = isset($vehicle_names[$vehicle])
                    ? $vehicle_names[$vehicle]
                    : ucfirst($vehicle);

                if (!empty($shipping_result['upgraded_by_meterage'])) {
                    $vehicle_by_weight = $shipping_result['vehicle_by_weight'];
                    $vehicle_by_meterage = $shipping_result['vehicle_by_meterage'];
                    $from_vehicle = isset($vehicle_names[$vehicle_by_weight]) ? $vehicle_names[$vehicle_by_weight] : ucfirst($vehicle_by_weight);
                    $to_vehicle = isset($vehicle_names[$vehicle_by_meterage]) ? $vehicle_names[$vehicle_by_meterage] : ucfirst($vehicle_by_meterage);
                    $vehicle_upgrade_notice = sprintf(
                        __('به دلیل بیشترین متراژ آیتم‌ها (%s متر)، نوع خودرو از %s به %s تغییر کرد.', 'woo-excel-mng'),
                        woo_excel_mng_format_number($max_meterage, 2, '.', ''),
                        $from_vehicle,
                        $to_vehicle
                    );
                }

                // منطق Premium
                if ($is_premium_mode) {
                    // محاسبه درصد (مثلاً 2%) از فاکتور
                    $shipping_percentage_amount = $cart_total * $shipping_percentage;

                    // اگر هزینه حمل <= درصد فاکتور: حمل رایگان
                    if ($base_shipping_cost <= $shipping_percentage_amount) {
                        $is_free_shipping = true;
                        $shipping_cost = 0;
                    } else {
                        // محاسبه مبلغ هدف برای حمل رایگان
                        $target_amount = $base_shipping_cost / $shipping_percentage;
                        $shipping_cost = $base_shipping_cost; // هزینه فعلی
                    }
                } else {
                    // حالت عادی: هزینه حمل از جدول
                    $shipping_cost = $base_shipping_cost;
                }
            }
        }

?>
        <div class="woo-excel-shipping-info-box woo-excel-shipping-payment-box woocommerce-billing-fields">
            <h3><?php _e('اطلاعات حمل‌ونقل', 'woo-excel-mng'); ?></h3>

            <!-- انتخاب شهر مقصد -->
            <div class="woo-excel-destination-selector">
                <?php
                woocommerce_form_field('woo_excel_destination_city', array(
                    'type' => 'select',
                    'class' => array('woo-excel-city-select'),
                    'label' => __('شهر مقصد', 'woo-excel-mng'),
                    'required' => true,
                    'options' => $city_options,
                ), $destination_city);
                ?>
            </div>

            <!-- جزئیات آیتم‌ها حذف شد -->

            <!-- اطلاعات حمل‌ونقل -->
            <?php if ($destination_city): ?>
                <div class="woo-excel-shipping-details">
                    <?php if ($vehicle_upgrade_notice): ?>
                        <div class="woo-excel-vehicle-change-alert">
                            <strong><?php _e('تغییر نوع خودرو', 'woo-excel-mng'); ?></strong>
                            <p><?php echo esc_html($vehicle_upgrade_notice); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($is_free_shipping): ?>
                        <div class="woo-excel-free-shipping-badge">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <strong><?php _e('حمل رایگان!', 'woo-excel-mng'); ?></strong>
                            <?php if ($is_premium_mode): ?>
                                <p><?php printf(__('هزینه حمل (%s) کمتر از %s%% مبلغ فاکتور (%s) است.', 'woo-excel-mng'), woo_excel_mng_format_price($base_shipping_cost), number_format($shipping_percentage * 100, 1), woo_excel_mng_format_price($shipping_percentage_amount)); ?></p>
                            <?php else: ?>
                                <p><?php _e('حمل شما رایگان است.', 'woo-excel-mng'); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php if ($vehicle && $base_shipping_cost > 0): ?>
                            <div class="woo-excel-shipping-info">
                                <p>
                                    <strong><?php _e('نوع وسیله:', 'woo-excel-mng'); ?></strong>
                                    <span class="vehicle-name"><?php echo esc_html($vehicle_name); ?></span>
                                </p>
                                <p>
                                    <strong><?php _e('هزینه حمل:', 'woo-excel-mng'); ?></strong>
                                    <span class="shipping-cost"><?php echo woo_excel_mng_format_price($shipping_cost); ?></span>
                                </p>

                                <?php if ($is_premium_mode && $target_amount > 0): ?>
                                    <!-- حالت Premium: پیشنهاد افزایش خرید -->
                                    <div class="woo-excel-premium-suggestion">
                                        <p class="premium-notice">
                                            <strong><?php _e('💡 پیشنهاد:', 'woo-excel-mng'); ?></strong>
                                        </p>
                                        <p>
                                            <?php
                                            printf(
                                                __('اگر خرید خود را به %s برسانید، حمل رایگان می‌شود!', 'woo-excel-mng'),
                                                '<strong>' . woo_excel_mng_format_price($target_amount) . '</strong>'
                                            );
                                            ?>
                                        </p>
                                        <p class="premium-details">
                                            <?php
                                            printf(
                                                __('هزینه حمل (%s) = %s%% × مبلغ فاکتور → مبلغ هدف = %s', 'woo-excel-mng'),
                                                woo_excel_mng_format_price($base_shipping_cost),
                                                number_format($shipping_percentage * 100, 1),
                                                woo_excel_mng_format_price($target_amount)
                                            );
                                            ?>
                                        </p>
                                        <p class="premium-remaining">
                                            <?php
                                            $remaining_for_free = $target_amount - $cart_total;
                                            printf(
                                                __('%s دیگر تا حمل رایگان', 'woo-excel-mng'),
                                                '<strong>' . woo_excel_mng_format_price($remaining_for_free) . '</strong>'
                                            );
                                            ?>
                                        </p>
                                    </div>
                                <?php elseif (!$is_premium_mode): ?>
                                    <!-- حالت عادی: نمایش پیشرفت حمل رایگان قدیمی -->
                                    <?php
                                    $free_shipping_threshold = floatval(get_option('woo_excel_mng_free_shipping_threshold', 20000000));
                                    if ($free_shipping_threshold > 0):
                                        $remaining = $free_shipping_threshold - $cart_total;
                                        $percentage = min(100, max(0, ($cart_total / $free_shipping_threshold) * 100));
                                    ?>
                                        <div class="woo-excel-free-shipping-progress">
                                            <p>
                                                <?php printf(
                                                    __('%s دیگر تا حمل رایگان', 'woo-excel-mng'),
                                                    woo_excel_mng_format_price($remaining)
                                                ); ?>
                                            </p>
                                            <div class="woo-excel-progress-bar">
                                                <div class="woo-excel-progress-fill" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                                            </div>
                                            <p class="progress-text">
                                                <?php printf(
                                                    __('مبلغ فعلی: %s / حد آستانه: %s', 'woo-excel-mng'),
                                                    woo_excel_mng_format_price($cart_total),
                                                    woo_excel_mng_format_price($free_shipping_threshold)
                                                ); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p class="woo-excel-no-route">
                                <?php _e('مسیر حمل‌ونقل برای این شهر یافت نشد.', 'woo-excel-mng'); ?>
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="woo-excel-select-city-notice">
                    <p><?php _e('لطفاً شهر مقصد را انتخاب کنید تا هزینه حمل محاسبه شود.', 'woo-excel-mng'); ?></p>
                </div>
            <?php endif; ?>
        </div>

    <?php
    }

    /**
     * حفظ مقدار اعشاری quantity در cart
     */
    public function preserve_decimal_quantity($quantity, $cart_item_key, $cart_item)
    {
        $product = $cart_item['data'];
        if ($product && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $formula = Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
            if ($formula) {
                // اطمینان از اینکه quantity به صورت float ذخیره می‌شود
                return floatval($quantity);
            }
        }
        return $quantity;
    }

    /**
     * بعد از به‌روزرسانی quantity در cart
     */
    public function after_cart_item_quantity_update($cart_item_key, $quantity, $old_quantity, $cart)
    {
        $cart_item = $cart->get_cart_item($cart_item_key);
        if (!$cart_item) {
            return;
        }

        $product = $cart_item['data'];
        if ($product && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $formula = Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
            if ($formula) {
                // اطمینان از اینکه quantity به صورت float ذخیره می‌شود
                $cart->cart_contents[$cart_item_key]['quantity'] = floatval($quantity);
            }
        }
    }

    /**
     * حفظ مقدار اعشاری quantity در update cart
     */
    public function preserve_decimal_in_cart_update($cart_updated)
    {
        if (!isset($_POST['cart']) || !is_array($_POST['cart'])) {
            return $cart_updated;
        }

        $cart = WC()->cart;

        foreach ($_POST['cart'] as $cart_item_key => $cart_item_data) {
            if (!isset($cart_item_data['qty'])) {
                continue;
            }

            $cart_item = $cart->get_cart_item($cart_item_key);
            if (!$cart_item) {
                continue;
            }

            $product = $cart_item['data'];
            if ($product && $product->is_type('variation')) {
                $parent_id = $product->get_parent_id();
                $formula = Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
                if ($formula) {
                    // تبدیل quantity به float قبل از ذخیره
                    $quantity = floatval($cart_item_data['qty']);
                    // ذخیره مستقیم در cart_contents
                    $cart->cart_contents[$cart_item_key]['quantity'] = $quantity;
                }
            }
        }

        return $cart_updated;
    }

    /**
     * حفظ مقدار اعشاری quantity در WooCommerce Blocks REST API
     */
    public function rest_cart_item_quantity($quantity, $cart_item, $cart_item_key)
    {
        $product = $cart_item['data'];
        if ($product && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $formula = Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
            if ($formula) {
                // در Blocks quantity باید integer باشد
                return 1;
            }
        }
        return $quantity;
    }

    /**
     * حفظ داده‌های اعشاری در WooCommerce Blocks REST API
     */
    public function rest_cart_item_data($cart_item_data, $cart_item)
    {
        $product = $cart_item['data'];
        if ($product && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $formula = Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
            if ($formula && isset($cart_item['quantity'])) {
                // در Blocks quantity باید integer باشد
                $cart_item_data['quantity'] = 1;
                // متراژ را هم به خروجی اضافه کن (برای استفاده‌های احتمالی در آینده)
                if (isset($cart_item[self::CART_ITEM_METERAGE_KEY])) {
                    $cart_item_data[self::CART_ITEM_METERAGE_KEY] = floatval($cart_item[self::CART_ITEM_METERAGE_KEY]);
                }
            }
        }
        return $cart_item_data;
    }

    /**
     * اضافه کردن script برای تنظیم step و min در cart
     */
    public function add_cart_quantity_script()
    {
        if (!is_cart()) {
            return;
        }
    ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // تنظیم step و min برای ورودی متراژ در cart
                function setupCartQuantityInputs() {
                    $('table.cart input.woo-excel-meterage-input').each(function() {
                        var $input = $(this);
                        var $row = $input.closest('tr.cart_item');

                        // بررسی اینکه آیا این محصول فرمول دارد
                        if ($row.find('.woo-excel-meterage-display').length > 0) {
                            // تنظیم step و min برای جلوگیری از گرد شدن
                            $input.attr({
                                'data-step': '<?php echo esc_js($this->get_meterage_step()); ?>',
                                'data-min': '<?php echo esc_js($this->get_meterage_min()); ?>',
                                'type': 'text',
                                'inputmode': 'decimal'
                            });
                        }
                    });
                }

                // اجرا هنگام بارگذاری صفحه
                setupCartQuantityInputs();

                // اجرا بعد از به‌روزرسانی سبد خرید
                $(document).on('updated_wc_div', function() {
                    setTimeout(setupCartQuantityInputs, 100);
                });
            });
        </script>
    <?php
    }

    /**
     * نمایش قیمت محاسبه شده در سبد خرید (قیمت واحد)
     */
    public function display_calculated_price($price, $cart_item, $cart_item_key)
    {
        if (isset($cart_item['woo_excel_calculated_price'])) {
            $calculated_price = floatval($cart_item['woo_excel_calculated_price']);
            $meterage = isset($cart_item[self::CART_ITEM_METERAGE_KEY]) ? floatval($cart_item[self::CART_ITEM_METERAGE_KEY]) : 0;

            if ($meterage > 0) {
                $unit_price = $calculated_price / $meterage;
                return sprintf(
                    '%s <small class="woo-excel-unit-price">/ %s</small>',
                    woo_excel_mng_format_price($unit_price),
                    esc_html__('متر', 'woo-excel-mng')
                );
            }

            return woo_excel_mng_format_price($calculated_price);
        }

        return $price;
    }

    /**
     * نمایش قیمت کل (subtotal) هر آیتم در سبد خرید
     */
    public function display_calculated_subtotal($subtotal, $cart_item, $cart_item_key)
    {
        if (isset($cart_item['woo_excel_calculated_price'])) {
            $calculated_price = floatval($cart_item['woo_excel_calculated_price']);
            // قیمت محاسبه شده برای متراژ وارد شده است، quantity همیشه 1
            return woo_excel_mng_format_price($calculated_price);
        }

        return $subtotal;
    }

    /**
     * حذف فیلدهای پیش‌فرض و نمایش فیلدهای مورد نیاز در تسویه حساب
     */
    public function customize_checkout_fields($fields) {
        $fields['billing'] = array(
            'billing_first_name' => array(
                'label' => __('نام', 'woo-excel-mng'),
                'required' => true,
                'class' => array('form-row-first'),
                'priority' => 10,
            ),
            'billing_last_name' => array(
                'label' => __('نام خانوادگی', 'woo-excel-mng'),
                'required' => true,
                'class' => array('form-row-last'),
                'priority' => 20,
            ),
            'billing_phone' => array(
                'label' => __('شماره همراه', 'woo-excel-mng'),
                'required' => true,
                'type' => 'tel',
                'class' => array('form-row-wide'),
                'priority' => 30,
            ),
            'billing_address_1' => array(
                'label' => __('آدرس', 'woo-excel-mng'),
                'required' => true,
                'type' => 'textarea',
                'class' => array('form-row-wide'),
                'priority' => 40,
            ),
        );

        $fields['shipping'] = array();
        $fields['order'] = array();

        return $fields;
    }

    /**
     * محاسبه قیمت و وزن بر اساس متراژ
     * استفاده از flag برای جلوگیری از حلقه بی‌نهایت
     */
    public function calculate_cart_totals($cart)
    {
        // جلوگیری از اجرا در admin (به جز AJAX)
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        // جلوگیری از حلقه بی‌نهایت
        if (WC()->session->get('woo_excel_calculating_totals')) {
            return;
        }

        // تنظیم flag
        WC()->session->set('woo_excel_calculating_totals', true);

        try {
            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                $product = $cart_item['data'];

                // فقط برای محصولات متغیر
                if (!$product->is_type('variation')) {
                    continue;
                }

                $variation_id = $product->get_id();
                $parent_id = $product->get_parent_id();

                // دریافت فرمول
                $formula = Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
                if (!$formula) {
                    continue;
                }

                // متراژ از cart item meta (quantity همیشه 1)
                $meterage = isset($cart_item[self::CART_ITEM_METERAGE_KEY]) ? floatval($cart_item[self::CART_ITEM_METERAGE_KEY]) : $this->get_meterage_min();
                if ($meterage < $this->get_meterage_min()) {
                    $meterage = $this->get_meterage_min();
                }

                // اجباری: qty باید integer باشد
                $cart->cart_contents[$cart_item_key]['quantity'] = 1;
                $cart->cart_contents[$cart_item_key][self::CART_ITEM_METERAGE_KEY] = $meterage;

                // دریافت متغیرها
                $variables = Woo_Excel_Mng_Formulas::get_variation_variables($variation_id, $meterage);

                if (!$variables) {
                    continue;
                }

                // محاسبه قیمت
                $calculated_price = Woo_Excel_Mng_Formulas::calculate_price($formula, $variables);

                if ($calculated_price !== null && $calculated_price > 0) {
                    // قیمت محاسبه شده برای همین متراژ است
                    $product->set_price($calculated_price);

                    // ذخیره قیمت محاسبه شده در cart item data
                    $cart->cart_contents[$cart_item_key]['woo_excel_calculated_price'] = $calculated_price;
                }

                // محاسبه وزن بر اساس متراژ (بدون تغییر وزن اصلی محصول)
                $base_weight = floatval($product->get_weight());
                if ($base_weight > 0) {
                    $total_weight = $base_weight * $meterage;

                    // ذخیره وزن محاسبه شده در cart item data (نه در محصول)
                    $cart->cart_contents[$cart_item_key]['woo_excel_calculated_weight'] = $total_weight;
                }
            }
        } finally {
            // حذف flag
            WC()->session->__unset('woo_excel_calculating_totals');
        }
    }
    
    // بلاک حمل رایگان قدیمی حذف شد - حالا در display_shipping_info_box نمایش داده می‌شود

    /**
     * اطمینان از محاسبه هزینه حمل
     */
    public function ensure_shipping_fee_calculated()
    {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        // اگر شهر انتخاب شده و هنوز fee اضافه نشده، اضافه کن
        $destination_city = WC()->session->get('woo_excel_destination_city');
        if ($destination_city) {
            $has_shipping_fee = false;
            $existing_fees = WC()->cart->get_fees();
            foreach ($existing_fees as $fee) {
                if (is_object($fee) && isset($fee->name) && strpos($fee->name, __('هزینه حمل', 'woo-excel-mng')) !== false) {
                    $has_shipping_fee = true;
                    break;
                }
            }

            if (!$has_shipping_fee) {
                WC()->cart->calculate_totals();
            }
        }
    }

    /**
     * اضافه کردن هزینه حمل به فاکتور
     */
    public function add_shipping_fee_to_cart($cart = null)
    {
        // اگر cart به عنوان پارامتر نیامده، از WC()->cart استفاده کن
        if (!$cart) {
            $cart = WC()->cart;
        }

        // جلوگیری از اجرا در admin (به جز AJAX)
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        // نمایش هزینه حمل فقط در مرحله تسویه حساب
        if (function_exists('is_cart') && is_cart()) {
            return;
        }

        // بررسی وجود سبد خرید
        if (!$cart || !is_object($cart) || $cart->is_empty()) {
            return;
        }

        // حذف fee قبلی (اگر وجود داشته باشد) برای جلوگیری از تکرار
        $existing_fees = $cart->get_fees();
        $fees_to_remove = array();
        foreach ($existing_fees as $fee_key => $fee) {
            if (is_object($fee) && isset($fee->name) && strpos($fee->name, __('هزینه حمل', 'woo-excel-mng')) !== false) {
                $fees_to_remove[] = $fee_key;
            } elseif (is_array($fee) && isset($fee['name']) && strpos($fee['name'], __('هزینه حمل', 'woo-excel-mng')) !== false) {
                $fees_to_remove[] = $fee_key;
            }
        }

        // حذف feeهای قدیمی
        if (!empty($fees_to_remove) && method_exists($cart, 'fees_api')) {
            foreach ($fees_to_remove as $fee_key) {
                $cart->fees_api()->remove_fee($fee_key);
            }
        }

        // دریافت شهر مبدا و مقصد
        $origin_city = get_option('woo_excel_mng_origin_city', 'تهران');
        $destination_city = WC()->session->get('woo_excel_destination_city');

        // اگر شهر مقصد تعیین نشده، هزینه اضافه نمی‌کنیم
        if (!$destination_city) {
            return;
        }

        // دریافت تنظیمات Premium
        $premium_threshold = floatval(get_option('woo_excel_mng_premium_threshold', 65000000));
        $shipping_percentage = floatval(get_option('woo_excel_mng_shipping_percentage', 2)) / 100;

        // محاسبه وزن کل سبد خرید
        $total_weight = 0;
        $cart_total = 0;

        foreach (WC()->cart->get_cart() as $cart_item) {
            if (isset($cart_item['woo_excel_calculated_weight'])) {
                $total_weight += floatval($cart_item['woo_excel_calculated_weight']);
            } else {
                $product = $cart_item['data'];
                $meterage = isset($cart_item[self::CART_ITEM_METERAGE_KEY])
                    ? floatval($cart_item[self::CART_ITEM_METERAGE_KEY])
                    : (isset($cart_item['quantity']) ? floatval($cart_item['quantity']) : 1);
                $product_weight = floatval($product->get_weight());
                if ($product_weight > 0) {
                    $total_weight += $product_weight * $meterage;
                }
            }

            // محاسبه قیمت
            if (isset($cart_item['woo_excel_calculated_price'])) {
                $cart_total += floatval($cart_item['woo_excel_calculated_price']);
            } else {
                $item_price = floatval($cart_item['data']->get_price());
                $quantity = isset($cart_item['quantity']) ? floatval($cart_item['quantity']) : 1;
                $cart_total += $item_price * $quantity;
            }
        }

        if ($total_weight <= 0) {
            return;
        }

        // محاسبه هزینه حمل از جدول
        $max_meterage = $this->get_cart_max_meterage(WC()->cart->get_cart());
        $shipping_result = Woo_Excel_Mng_Shipping::calculate_shipping_cost(
            $origin_city,
            $destination_city,
            $total_weight,
            $max_meterage
        );

        if (!$shipping_result) {
            return;
        }

        $base_shipping_cost = floatval($shipping_result['cost']);
        $vehicle = $shipping_result['vehicle'];

        $vehicle_names = array(
            'peykan' => 'پیکان',
            'mazda' => 'مزدا',
            'nissan' => 'نیسان'
        );
        $vehicle_name = isset($vehicle_names[$vehicle])
            ? $vehicle_names[$vehicle]
            : ucfirst($vehicle);

        // منطق Premium
        $is_premium_mode = ($cart_total >= $premium_threshold);
        $shipping_cost = 0;

        if ($is_premium_mode) {
            // محاسبه درصد از فاکتور
            $shipping_percentage_amount = $cart_total * $shipping_percentage;

            // اگر هزینه حمل <= درصد فاکتور: حمل رایگان
            if ($base_shipping_cost <= $shipping_percentage_amount) {
                $shipping_cost = 0; // حمل رایگان
            } else {
                $shipping_cost = $base_shipping_cost; // هزینه فعلی
            }
        } else {
            // حالت عادی: هزینه حمل از جدول
            $shipping_cost = $base_shipping_cost;
        }

        // اضافه کردن هزینه به فاکتور (فقط اگر بیشتر از 0 باشد)
        if ($shipping_cost > 0) {
            // استفاده از API WooCommerce برای اضافه کردن fee
            $fee_name = sprintf(__('هزینه حمل (%s)', 'woo-excel-mng'), $vehicle_name);

            if (method_exists($cart, 'add_fee')) {
                // روش قدیمی (سازگار با WooCommerce قدیمی)
                $cart->add_fee($fee_name, $shipping_cost, false);
            } elseif (method_exists($cart, 'fees_api')) {
                // استفاده از Fees API جدید (WooCommerce 3.2+)
                $cart->fees_api()->add_fee(array(
                    'name' => $fee_name,
                    'amount' => $shipping_cost,
                    'taxable' => false
                ));
            } else {
                // روش جایگزین: استفاده مستقیم از WC()->cart
                if (WC()->cart && method_exists(WC()->cart, 'add_fee')) {
                    WC()->cart->add_fee($fee_name, $shipping_cost, false);
                }
            }
        }
    }

    /**
     * محاسبه هزینه حمل‌ونقل
     * فقط اگر شهر مقصد تعیین شده باشد، نرخ‌های سفارشی را اعمال می‌کند
     */
    public function calculate_shipping_rates($rates, $package)
    {
        // دریافت شهر مبدا و مقصد
        $origin_city = get_option('woo_excel_mng_origin_city', 'تهران');
        $destination_city = WC()->session->get('woo_excel_destination_city');

        // اگر شهر مقصد تعیین نشده، نرخ‌های پیش‌فرض را برگردان (بدون تغییر)
        if (!$destination_city) {
            return $rates;
        }

        // محاسبه وزن کل سبد خرید (از cart item data)
        $total_weight = 0;
        foreach (WC()->cart->get_cart() as $cart_item) {
            // اول وزن محاسبه شده را بررسی کن
            // توجه: woo_excel_calculated_weight قبلاً شامل quantity (meterage) شده است
            if (isset($cart_item['woo_excel_calculated_weight'])) {
                $total_weight += floatval($cart_item['woo_excel_calculated_weight']);
            } else {
                // در غیر این صورت از وزن محصول استفاده کن
                $product = $cart_item['data'];
                $product_weight = floatval($product->get_weight());
                $meterage = isset($cart_item[self::CART_ITEM_METERAGE_KEY]) ? floatval($cart_item[self::CART_ITEM_METERAGE_KEY]) : (isset($cart_item['quantity']) ? floatval($cart_item['quantity']) : 1);
                if ($product_weight > 0) {
                    $total_weight += $product_weight * $meterage;
                }
            }
        }

        // بررسی آستانه حمل رایگان
        $cart_total = WC()->cart->get_subtotal();
        if (Woo_Excel_Mng_Shipping::check_free_shipping($cart_total)) {
            // اگر حمل رایگان است، نرخ رایگان اضافه کن
            $method_id = 'woo_excel_free_shipping';
            $rate = new WC_Shipping_Rate(
                $method_id,
                __('حمل رایگان', 'woo-excel-mng'),
                0,
                array(),
                $method_id
            );

            // اضافه کردن به نرخ‌های موجود (نه جایگزینی)
            $rates[$method_id] = $rate;
            return $rates;
        }

        // محاسبه هزینه حمل‌ونقل
        $max_meterage = $this->get_cart_max_meterage(WC()->cart->get_cart());
        $shipping_cost = Woo_Excel_Mng_Shipping::calculate_shipping_cost($origin_city, $destination_city, $total_weight, $max_meterage);

        if (!$shipping_cost || $shipping_cost['cost'] <= 0) {
            return $rates;
        }

        // نام وسیله نقلیه به فارسی
        $vehicle_names = array(
            'peykan' => 'پیکان',
            'mazda' => 'مزدا',
            'nissan' => 'نیسان'
        );
        $vehicle_name = isset($vehicle_names[$shipping_cost['vehicle']])
            ? $vehicle_names[$shipping_cost['vehicle']]
            : ucfirst($shipping_cost['vehicle']);

        // ایجاد نرخ حمل‌ونقل سفارشی
        $method_id = 'woo_excel_custom_shipping';
        $rate = new WC_Shipping_Rate(
            $method_id,
            sprintf(__('حمل‌ونقل (%s)', 'woo-excel-mng'), $vehicle_name),
            $shipping_cost['cost'],
            array(),
            $method_id
        );

        // اضافه کردن به نرخ‌های موجود
        $rates[$method_id] = $rate;

        return $rates;
    }

    /**
     * اضافه کردن label برای quantity در صفحه محصول
     */
    public function add_quantity_label()
    {
        global $product;
        if ($product && $product->is_type('variable')) {
            $formula = Woo_Excel_Mng_Formulas::get_product_formula($product->get_id());
            if ($formula) {
                echo '<style>
                    .woocommerce-variation-add-to-cart .quantity label {
                        display: block;
                        margin-bottom: 5px;
                        font-weight: 600;
                    }
                    .woocommerce-variation-add-to-cart .quantity label:before {
                        content: "متراژ (متر): ";
                    }
                </style>';
            }
        }
    }

    /**
     * AJAX: محاسبه قیمت
     */
    public function ajax_calculate_price()
    {
        check_ajax_referer('woo_excel_mng_frontend_nonce', 'nonce');

        $variation_id = isset($_POST['variation_id']) ? intval($_POST['variation_id']) : 0;
        $meterage_raw = isset($_POST['meterage']) ? $this->normalize_decimal_input($_POST['meterage']) : '';
        $meterage = $this->normalize_meterage_value($meterage_raw);

        if ($variation_id <= 0 || $meterage < $this->get_meterage_min()) {
            wp_send_json_error(__('داده‌های نامعتبر.', 'woo-excel-mng'));
        }

        // دریافت محصول والد
        $variation = wc_get_product($variation_id);
        if (!$variation || !$variation->is_type('variation')) {
            wp_send_json_error(__('Variation یافت نشد.', 'woo-excel-mng'));
        }

        $parent_id = $variation->get_parent_id();

        // دریافت فرمول
        $formula = Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
        if (!$formula) {
            wp_send_json_error(__('فرمول برای این محصول تعریف نشده است.', 'woo-excel-mng'));
        }

        // دریافت متغیرها
        $variables = Woo_Excel_Mng_Formulas::get_variation_variables($variation_id, $meterage);
        if (!$variables) {
            wp_send_json_error(__('خطا در دریافت اطلاعات Variation.', 'woo-excel-mng'));
        }

        // Debug: برای بررسی
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Woo Excel Mng AJAX - Formula: ' . $formula);
            error_log('Woo Excel Mng AJAX - Variables: ' . print_r($variables, true));
        }

        // محاسبه قیمت
        $calculated_price = Woo_Excel_Mng_Formulas::calculate_price($formula, $variables);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Woo Excel Mng AJAX - Calculated Price: ' . $calculated_price);
        }

        if ($calculated_price === null || $calculated_price === false) {
            wp_send_json_error(__('خطا در محاسبه قیمت. لطفاً فرمول و متغیرها را بررسی کنید.', 'woo-excel-mng'));
        }

        // محاسبه وزن
        $base_weight = floatval($variation->get_weight());
        $total_weight = $base_weight * $meterage;

        wp_send_json_success(array(
            'price' => $calculated_price,
            'formatted_price' => woo_excel_mng_format_price($calculated_price),
            'weight' => $total_weight,
            'formatted_weight' => wc_format_weight($total_weight)
        ));
    }

    /**
     * AJAX: به‌روزرسانی آیتم سبد خرید
     */
    public function ajax_update_cart_item()
    {
        check_ajax_referer('woo_excel_mng_frontend_nonce', 'nonce');

        $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
        // دریافت meterage (پشتیبانی از پارامتر قدیمی quantity)
        $meterage = 0;
        $meterage_raw = null;
        if (isset($_POST[self::CART_ITEM_METERAGE_KEY])) {
            $meterage_raw = $_POST[self::CART_ITEM_METERAGE_KEY];
        } elseif (isset($_POST['meterage'])) {
            $meterage_raw = $_POST['meterage'];
        } elseif (isset($_POST['quantity'])) {
            $meterage_raw = $_POST['quantity'];
        }

        if ($meterage_raw !== null) {
            $meterage_raw = $this->normalize_decimal_input($meterage_raw);
            $meterage = $this->normalize_meterage_value($meterage_raw);
        }

        if (empty($cart_item_key) || $meterage < $this->get_meterage_min()) {
            wp_send_json_error(__('داده‌های نامعتبر.', 'woo-excel-mng'));
        }

        // به‌روزرسانی quantity
        $cart = WC()->cart;
        $cart_item = $cart->get_cart_item($cart_item_key);

        if (!$cart_item) {
            wp_send_json_error(__('آیتم در سبد خرید یافت نشد.', 'woo-excel-mng'));
        }

        // ذخیره متراژ در meta و qty=1
        $cart->cart_contents[$cart_item_key][self::CART_ITEM_METERAGE_KEY] = floatval($meterage);
        $cart->cart_contents[$cart_item_key]['quantity'] = 1;

        // محاسبه مجدد قیمت
        $product = $cart_item['data'];
        if ($product && $product->is_type('variation')) {
            $variation_id = $product->get_id();
            $parent_id = $product->get_parent_id();

            $formula = Woo_Excel_Mng_Formulas::get_product_formula($parent_id);
            if ($formula) {
                $variables = Woo_Excel_Mng_Formulas::get_variation_variables($variation_id, $meterage);
                if ($variables) {
                    $calculated_price = Woo_Excel_Mng_Formulas::calculate_price($formula, $variables);
                    if ($calculated_price !== null && $calculated_price > 0) {
                        // به‌روزرسانی قیمت در cart item
                        $cart->cart_contents[$cart_item_key]['woo_excel_calculated_price'] = $calculated_price;
                        $product->set_price($calculated_price);

                        // محاسبه وزن
                        $base_weight = floatval($product->get_weight());
                        $total_weight = $base_weight * $meterage;
                        $cart->cart_contents[$cart_item_key]['woo_excel_calculated_weight'] = $total_weight;
                    }
                }
            }
        }

        // محاسبه مجدد totals
        $cart->calculate_totals();

        // دریافت اطلاعات به‌روزرسانی شده
        $updated_item = $cart->get_cart_item($cart_item_key);
        $item_subtotal = 0;
        if (isset($updated_item['woo_excel_calculated_price'])) {
            // qty=1
            $item_subtotal = floatval($updated_item['woo_excel_calculated_price']);
        }

        wp_send_json_success(array(
            'item_subtotal' => $item_subtotal,
            'formatted_item_subtotal' => woo_excel_mng_format_price($item_subtotal),
            'cart_total' => $cart->get_subtotal(),
            'formatted_cart_total' => woo_excel_mng_format_price($cart->get_subtotal()),
            'cart_total_with_shipping' => $cart->get_total(''),
            'formatted_cart_total_with_shipping' => woo_excel_mng_format_price($cart->get_total(''))
        ));
    }

    /**
     * AJAX: ذخیره شهر مقصد
     */
    public function ajax_save_destination_city()
    {
        check_ajax_referer('woo_excel_mng_frontend_nonce', 'nonce');

        $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';

        if (empty($city)) {
            wp_send_json_error(__('شهر نامعتبر است.', 'woo-excel-mng'));
        }

        // ذخیره شهر در session
        WC()->session->set('woo_excel_destination_city', $city);

        // محاسبه مجدد totals برای اعمال هزینه حمل
        if (WC()->cart && !WC()->cart->is_empty()) {
            WC()->cart->calculate_totals();
        }

        wp_send_json_success(array(
            'message' => __('شهر مقصد ذخیره شد.', 'woo-excel-mng'),
            'city' => $city
        ));
    }

    /**
     * اضافه کردن فیلد انتخاب شهر مقصد در صفحه تسویه حساب
     */
    public function add_destination_city_field($checkout)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'woo_excel_shipping_routes';

        // دریافت لیست شهرهای منحصر به فرد
        $cities = $wpdb->get_col("SELECT DISTINCT destination_city FROM $table_name WHERE is_active = 1 ORDER BY destination_city");

        if (empty($cities)) {
            return;
        }

        $options = array('' => __('-- انتخاب شهر --', 'woo-excel-mng'));
        foreach ($cities as $city) {
            $options[$city] = $city;
        }

        woocommerce_form_field('woo_excel_destination_city', array(
            'type' => 'select',
            'class' => array('form-row-wide', 'address-field'),
            'label' => __('شهر مقصد', 'woo-excel-mng'),
            'required' => true,
            'options' => $options,
            'default' => WC()->session->get('woo_excel_destination_city')
        ), WC()->session->get('woo_excel_destination_city'));

        // اسکریپت برای به‌روزرسانی نرخ‌های حمل‌ونقل
    ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#woo_excel_destination_city').on('change', function() {
                    var city = $(this).val();
                    if (city) {
                        $('body').trigger('update_checkout');
                    }
                });
            });
        </script>
<?php
    }

    /**
     * اعتبارسنجی فیلد شهر مقصد
     */
    public function validate_destination_city()
    {
        if (empty($_POST['woo_excel_destination_city'])) {
            wc_add_notice(__('لطفاً شهر مقصد را انتخاب کنید.', 'woo-excel-mng'), 'error');
        } else {
            // ذخیره در session
            WC()->session->set('woo_excel_destination_city', sanitize_text_field($_POST['woo_excel_destination_city']));
        }
    }

    /**
     * ذخیره شهر مقصد در سفارش
     */
    public function save_destination_city($order_id)
    {
        if (!empty($_POST['woo_excel_destination_city'])) {
            $city = sanitize_text_field($_POST['woo_excel_destination_city']);
            update_post_meta($order_id, '_woo_excel_destination_city', $city);
        }
    }

    /**
     * انتقال جزئیات پرداخت به پایین سفارش
     */
    public function reposition_checkout_billing_fields()
    {
        if (!function_exists('is_checkout') || !is_checkout()) {
            return;
        }

        if (function_exists('woocommerce_checkout_billing')) {
            remove_action('woocommerce_checkout_billing', 'woocommerce_checkout_billing', 10);
            add_action('woocommerce_checkout_after_order_review', 'woocommerce_checkout_billing', 10);
        }
    }

    /**
     * غیرفعال کردن کد تخفیف در سبد خرید
     */
    public function disable_cart_coupons($enabled)
    {
        if (function_exists('is_cart') && is_cart()) {
            return false;
        }

        return $enabled;
    }

    /**
     * مخفی کردن حمل‌ونقل در سبد خرید
     */
    public function disable_cart_shipping_display($needs_shipping)
    {
        if (function_exists('is_cart') && is_cart()) {
            return false;
        }

        return $needs_shipping;
    }
}

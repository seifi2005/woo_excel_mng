/**
 * اسکریپت‌های Front-end افزونه
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {

        var meterageMin = (typeof wooExcelMngFrontend !== 'undefined' && wooExcelMngFrontend.meterage_min)
            ? parseFloat(wooExcelMngFrontend.meterage_min)
            : 0.1;
        var meterageStep = (typeof wooExcelMngFrontend !== 'undefined' && wooExcelMngFrontend.meterage_step)
            ? parseFloat(wooExcelMngFrontend.meterage_step)
            : 0.01;

        // نرمال‌سازی ورودی اعشاری (پشتیبانی از ارقام فارسی/عربی)
        function normalizeDecimalInput(value) {
            if (value === null || value === undefined) {
                return '';
            }

            var str = String(value).replace(/\s+/g, '');
            var map = {
                '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
                '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
                '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
                '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
                '٫': '.', ',': '.', '٬': ''
            };

            return str.replace(/[۰-۹٠-٩٫٬,]/g, function(ch) {
                return Object.prototype.hasOwnProperty.call(map, ch) ? map[ch] : ch;
            });
        }

        // نرمال‌سازی متراژ بر اساس گام
        function normalizeMeterageValue(value) {
            var meterage = parseFloat(value);
            if (isNaN(meterage)) {
                return meterage;
            }

            if (meterageStep > 0) {
                meterage = Math.round(meterage / meterageStep) * meterageStep;
            }

            return parseFloat(meterage.toFixed(2));
        }

        // اعمال تنظیمات اعشاری روی ورودی تعداد در صفحه محصول (حتی قبل از انتخاب وارییشن)
        if (typeof wooExcelMngFrontend !== 'undefined' && wooExcelMngFrontend.has_formula_product) {
            var $productQtyInput = $('.quantity input.qty');
            if ($productQtyInput.length) {
                $productQtyInput.attr({
                    'step': meterageStep,
                    'min': meterageMin,
                    'inputmode': 'decimal'
                });
            }
        }
        
        // ===== مدیریت فیلد quantity (متراژ) در صفحه محصول =====
        // تغییر label quantity به متراژ فقط برای وارییشن‌هایی که فرمول دارند
        $(document).on('found_variation', function(event, variation) {
            var $quantityInput = $('.quantity input.qty');
            var $quantityLabel = $('.quantity label');
            var $form = $('form.variations_form');

            var hasFormula = !!(variation && variation.woo_excel_has_formula);
            if ($form.length) {
                $form.data('woo_excel_has_formula', hasFormula);
            }

            if (!hasFormula) {
                if ($quantityLabel.length) {
                    $quantityLabel.text('تعداد:');
                }
                $('.woo-excel-price-preview').hide();
                return;
            }

            // تغییر label به متراژ
            if ($quantityLabel.length) {
                $quantityLabel.text('متراژ (متر):');
            } else {
                $quantityInput.before('<label for="quantity">متراژ (متر):</label>');
            }

            // تنظیم step و min برای مقدار اعشاری
            $quantityInput.attr({
                'step': meterageStep,
                'min': meterageMin,
                'inputmode': 'decimal'
            });

            // اضافه کردن preview قیمت
            if ($('.woo-excel-price-preview').length === 0) {
                $quantityInput.after('<div class="woo-excel-price-preview" style="display:none; margin-top: 10px; padding: 10px; background: #e8f5e9; border: 1px solid #4caf50; border-radius: 4px;"><strong>قیمت نهایی:</strong> <span class="woo-excel-calculated-price"></span></div>');
            }
        });
        
        // مخفی کردن preview وقتی variation لغو شد
        $(document).on('reset_data', function() {
            var $form = $('form.variations_form');
            if ($form.length) {
                $form.data('woo_excel_has_formula', false);
            }
            $('.woo-excel-price-preview').hide();
            $('.quantity label').text('تعداد:');
        });
        
        // محاسبه قیمت هنگام تغییر quantity (متراژ)
        var calculationTimeout;
        $(document).on('input change', '.quantity input.qty', function() {
            var $input = $(this);
            var meterageValue = $input.val();
            var normalizedValue = normalizeDecimalInput(meterageValue);
            if (normalizedValue !== meterageValue) {
                $input.val(normalizedValue);
                meterageValue = normalizedValue;
            }

            var $form = $('form.variations_form');
            if (!$form.length || !$form.data('woo_excel_has_formula')) {
                return;
            }
            
            // پاک کردن timeout قبلی
            clearTimeout(calculationTimeout);
            
            // بررسی اعتبار - پشتیبانی از مقادیر اعشاری
            var meterage = normalizeMeterageValue(meterageValue);
            if (isNaN(meterage) || meterage < meterageMin) {
                $('.woo-excel-price-preview').hide();
                return;
            }
            
            // بررسی اینکه مقدار اعشاری معتبر است (حداکثر 2 رقم اعشار)
            $input.val(meterage.toFixed(2));
            
            // دریافت Variation ID
            var variationId = $('input[name="variation_id"]').val();
            if (!variationId) {
                return;
            }
            
            // فقط برای محصولات با فرمول
            if (!$form.data('woo_excel_has_formula')) {
                return;
            }
            
            // نمایش پیام در حال محاسبه
            var $preview = $('.woo-excel-price-preview');
            $preview.show();
            $('.woo-excel-calculated-price').text(wooExcelMngFrontend.strings.calculating);
            
            // تاخیر برای کاهش درخواست‌های AJAX
            calculationTimeout = setTimeout(function() {
                calculatePrice(variationId, meterage);
            }, 500);
        });
        
        // ===== تابع محاسبه قیمت =====
        function calculatePrice(variationId, meterage) {
            $.ajax({
                url: wooExcelMngFrontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'woo_excel_mng_calculate_price',
                    nonce: wooExcelMngFrontend.nonce,
                    variation_id: variationId,
                    meterage: meterage
                },
                success: function(response) {
                    if (response.success) {
                        $('.woo-excel-calculated-price').html(response.data.formatted_price);
                        $('.woo-excel-price-preview').show();
                    } else {
                        $('.woo-excel-price-preview').hide();
                        console.error('خطا:', response.data);
                    }
                },
                error: function() {
                    $('.woo-excel-price-preview').hide();
                    console.error('خطا در ارتباط با سرور');
                }
            });
        }
        
        // ===== اعتبارسنجی قبل از افزودن به سبد =====
        $('form.cart').on('submit', function(e) {
            var $quantityInput = $('.quantity input.qty');
            var $form = $('form.variations_form');
            
            // فقط برای محصولات با فرمول
            if ($form.length && $form.data('woo_excel_has_formula') && $quantityInput.length) {
                var meterageValue = $quantityInput.val();
                var normalizedValue = normalizeDecimalInput(meterageValue);
                if (normalizedValue !== meterageValue) {
                    $quantityInput.val(normalizedValue);
                    meterageValue = normalizedValue;
                }

                var meterage = normalizeMeterageValue(meterageValue);
                
                if (isNaN(meterage) || meterage < meterageMin) {
                    e.preventDefault();
                    alert(wooExcelMngFrontend.strings.enter_meterage || 'لطفاً متراژ را وارد کنید.');
                    $quantityInput.focus();
                    return false;
                }

            // مدل جدید: quantity باید integer باشد => 1
            // اگر ورودی سفارشی است، همان را نگه دار
            if ($quantityInput.attr('name') === 'woo_excel_meterage') {
                if ($(this).find('input[name="quantity"]').length === 0) {
                    $(this).append('<input type="hidden" name="quantity" value="1">');
                }
            } else {
                $(this).find('input[name="woo_excel_meterage"]').remove();
                $(this).append('<input type="hidden" name="woo_excel_meterage" value="' + meterage.toFixed(2) + '">');
                $quantityInput.val('1');
            }
            }
        });
        
        // ===== تنظیم step و min برای ورودی متراژ در سبد خرید =====
        function setupCartQuantityInputs() {
            $('table.cart input.woo-excel-meterage-input').each(function() {
                var $input = $(this);
                var $row = $input.closest('tr.cart_item');
                
                // بررسی اینکه آیا این محصول فرمول دارد (با بررسی وجود label متراژ)
                if ($row.find('.woo-excel-meterage-display').length > 0) {
                    // تنظیم step و min برای جلوگیری از گرد شدن
                    $input.attr({
                        'step': meterageStep,
                        'min': meterageMin,
                        'type': 'number'
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
        
        // ===== مدیریت تغییر quantity در سبد خرید =====
        var updateCartTimeout;
        
        $(document).on('change blur', 'table.cart input.woo-excel-meterage-input', function(e) {
            var $input = $(this);
            var $row = $input.closest('tr.cart_item');
            
            // بررسی اینکه آیا این محصول فرمول دارد
            if ($row.find('.woo-excel-meterage-display').length === 0) {
                return; // اگر فرمول ندارد، از ووکامرس استفاده کن
            }
            
            // پیدا کردن cart_item_key از input name: woo_excel_meterage[KEY]
            var cartItemKey = null;
            var nameAttr = $input.attr('name');
            if (nameAttr) {
                var match = nameAttr.match(/woo_excel_meterage\[([^\]]+)\]/);
                if (match) {
                    cartItemKey = match[1];
                }
            }
            
            if (!cartItemKey) {
                return;
            }
            
            var meterageValue = $input.val();
            var normalizedValue = normalizeDecimalInput(meterageValue);
            if (normalizedValue !== meterageValue) {
                $input.val(normalizedValue);
                meterageValue = normalizedValue;
            }
            var meterage = normalizeMeterageValue(meterageValue);
            
            // بررسی اعتبار
            if (isNaN(meterage) || meterage < meterageMin) {
                alert('متراژ باید حداقل ' + meterageMin + ' متر باشد.');
                var oldValue = $input.data('old-value');
                if (oldValue) {
                    $input.val(oldValue);
                } else {
                    $input.val('1');
                }
                return;
            }
            
            // ذخیره مقدار قبلی
            $input.data('old-value', meterageValue);
            
            // به‌روزرسانی نمایش بر اساس گام
            $input.val(meterage.toFixed(2));
            
            // پاک کردن timeout قبلی
            clearTimeout(updateCartTimeout);
            
            // نمایش loading
            $row.addClass('woo-excel-updating');
            
            // تاخیر برای کاهش درخواست‌های AJAX
            updateCartTimeout = setTimeout(function() {
                updateCartItem(cartItemKey, meterage, $row);
            }, 500);
        });
        
        // ===== تابع به‌روزرسانی آیتم سبد خرید =====
        function updateCartItem(cartItemKey, meterage, $row) {
            $.ajax({
                url: wooExcelMngFrontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'woo_excel_mng_update_cart_item',
                    nonce: wooExcelMngFrontend.nonce,
                    cart_item_key: cartItemKey,
                    meterage: parseFloat(meterage).toFixed(2) // ارسال به صورت string با 2 رقم اعشار
                },
                success: function(response) {
                    $row.removeClass('woo-excel-updating');
                    if (response.success) {
                        // به‌روزرسانی سبد خرید
                        $('body').trigger('update_cart');
                    } else {
                        alert('خطا: ' + (response.data || 'خطا در به‌روزرسانی سبد خرید'));
                        // بازگرداندن مقدار قبلی
                        var oldValue = $row.find('input.woo-excel-meterage-input').data('old-value');
                        if (oldValue) {
                            $row.find('input.woo-excel-meterage-input').val(oldValue);
                        }
                    }
                },
                error: function() {
                    $row.removeClass('woo-excel-updating');
                    alert('خطا در ارتباط با سرور');
                    // بازگرداندن مقدار قبلی
                    var oldValue = $row.find('input.woo-excel-meterage-input').data('old-value');
                    if (oldValue) {
                        $row.find('input.woo-excel-meterage-input').val(oldValue);
                    }
                }
            });
        }
        
        // ===== به‌روزرسانی بلاک حمل رایگان در سبد خرید =====
        // به‌روزرسانی هنگام تغییر تعداد یا حذف آیتم
        $(document).on('updated_wc_div', function() {
            // این event توسط ووکامرس بعد از به‌روزرسانی سبد خرید فراخوانی می‌شود
            // بلاک حمل رایگان به صورت خودکار به‌روزرسانی می‌شود چون در PHP رندر می‌شود
        });
        
        // به‌روزرسانی هنگام حذف آیتم
        $(document).on('click', '.remove', function() {
            // ووکامرس خودش سبد خرید را به‌روزرسانی می‌کند
            // بلاک حمل رایگان به صورت خودکار به‌روزرسانی می‌شود
        });
        
        // ===== مدیریت انتخاب شهر مقصد =====
        function saveDestinationCity(city, $select) {
            if (!city) {
                return;
            }

            if ($select && $select.length) {
                $select.prop('disabled', true);
            }

            $.ajax({
                url: wooExcelMngFrontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'woo_excel_mng_save_destination_city',
                    nonce: wooExcelMngFrontend.nonce,
                    city: city
                },
                success: function(response) {
                    if (response && response.success) {
                        if ($('body').hasClass('woocommerce-cart')) {
                            window.location.reload();
                        } else {
                            $('body').trigger('update_checkout');
                        }
                    } else {
                        var errorMsg = (response && response.data) ? response.data : 'خطا در ذخیره شهر';
                        alert('خطا: ' + errorMsg);
                        if ($select && $select.length) {
                            $select.prop('disabled', false);
                        }
                    }
                },
                error: function() {
                    alert('خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.');
                    if ($select && $select.length) {
                        $select.prop('disabled', false);
                    }
                }
            });
        }

        $(document).on('change', '#woo_excel_destination_city, .woo-excel-city-select', function() {
            var $select = $(this);
            var city = $select.val();
            saveDestinationCity(city, $select);
        });
        
    });
    
})(jQuery);

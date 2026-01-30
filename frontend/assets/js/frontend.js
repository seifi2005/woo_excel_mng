/**
 * اسکریپت‌های Front-end افزونه
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
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
                'step': '0.01',
                'min': '0.1',
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

            var $form = $('form.variations_form');
            if (!$form.length || !$form.data('woo_excel_has_formula')) {
                return;
            }
            
            // پاک کردن timeout قبلی
            clearTimeout(calculationTimeout);
            
            // بررسی اعتبار - پشتیبانی از مقادیر اعشاری
            var meterage = parseFloat(meterageValue);
            if (isNaN(meterage) || meterage < 0.1) {
                $('.woo-excel-price-preview').hide();
                return;
            }
            
            // بررسی اینکه مقدار اعشاری معتبر است (حداکثر 2 رقم اعشار)
            if (meterageValue.indexOf('.') !== -1) {
                var decimalPart = meterageValue.split('.')[1];
                if (decimalPart && decimalPart.length > 2) {
                    // محدود کردن به 2 رقم اعشار
                    meterage = parseFloat(meterage.toFixed(2));
                    $input.val(meterage.toFixed(2));
                }
            }
            
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
                var meterage = parseFloat(meterageValue);
                
                if (isNaN(meterage) || meterage < 0.1) {
                    e.preventDefault();
                    alert(wooExcelMngFrontend.strings.enter_meterage || 'لطفاً متراژ را وارد کنید.');
                    $quantityInput.focus();
                    return false;
                }
                
                // محدود کردن به 2 رقم اعشار
                if (meterageValue.indexOf('.') !== -1) {
                    var decimalPart = meterageValue.split('.')[1];
                    if (decimalPart && decimalPart.length > 2) {
                        meterage = parseFloat(meterage.toFixed(2));
                    }
                }

                // مدل جدید: quantity باید integer باشد => 1
                // متراژ را جداگانه ارسال می‌کنیم
                $(this).find('input[name="woo_excel_meterage"]').remove();
                $(this).append('<input type="hidden" name="woo_excel_meterage" value="' + meterage.toFixed(2) + '">');
                $quantityInput.val('1');
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
                        'step': '0.01',
                        'min': '0.1',
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
            var meterage = parseFloat(meterageValue);
            
            // بررسی اعتبار
            if (isNaN(meterage) || meterage < 0.1) {
                alert('متراژ باید حداقل 0.1 متر باشد.');
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
            
            // محدود کردن به 2 رقم اعشار (فقط برای نمایش)
            if (meterageValue.indexOf('.') !== -1) {
                var decimalPart = meterageValue.split('.')[1];
                if (decimalPart && decimalPart.length > 2) {
                    meterage = parseFloat(meterage.toFixed(2));
                    $input.val(meterage.toFixed(2));
                }
            }
            
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
        if ($('#woo_excel_destination_city').length) {
            $('#woo_excel_destination_city').on('change', function() {
                var city = $(this).val();
                
                if (!city) {
                    return;
                }
                
                // ذخیره در session
                $.ajax({
                    url: wooExcelMngFrontend.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'woo_excel_mng_save_destination_city',
                        nonce: wooExcelMngFrontend.nonce,
                        city: city
                    },
                    success: function() {
                        // به‌روزرسانی نرخ‌های حمل‌ونقل
                        $('body').trigger('update_checkout');
                    }
                });
            });
        }
        
    });
    
})(jQuery);

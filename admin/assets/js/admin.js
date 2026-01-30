/**
 * اسکریپت‌های پیشخوان افزونه
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // ===== مدیریت انتخاب فایل =====
        $('#products_file, #shipping_file').on('change', function() {
            var fileName = $(this).val().split('\\').pop();
            var fileLabel = $(this).closest('.form-group').find('.file-name');
            if (fileName) {
                fileLabel.text('✓ ' + fileName);
                fileLabel.css('color', '#00a32a');
            } else {
                fileLabel.text('');
            }
        });
        
        // ===== مدیریت ذخیره مسیرهای حمل‌ونقل =====
        $('.save-route').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            var routeId = $button.data('route-id');
            var $row = $button.closest('tr');
            
            var data = {
                action: 'woo_excel_mng_save_route',
                nonce: wooExcelMng.nonce,
                route_id: routeId,
                peykan_price: $row.find('input[data-field="peykan_price"]').val(),
                mazda_price: $row.find('input[data-field="mazda_price"]').val(),
                nissan_price: $row.find('input[data-field="nissan_price"]').val(),
                is_active: $row.find('.route-active').is(':checked') ? 1 : 0
            };
            
            $button.prop('disabled', true).text(wooExcelMng.strings.processing);
            
            $.ajax({
                url: wooExcelMng.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        $button.prop('disabled', false).text('ذخیره شد ✓');
                        setTimeout(function() {
                            $button.text('ذخیره');
                        }, 2000);
                    } else {
                        alert('خطا: ' + (response.data || 'خطای نامشخص'));
                        $button.prop('disabled', false).text('ذخیره');
                    }
                },
                error: function() {
                    alert('خطا در ارتباط با سرور');
                    $button.prop('disabled', false).text('ذخیره');
                }
            });
        });
        
        // ===== مدیریت ویرایش فرمول =====
        $('.edit-formula').on('click', function() {
            var formulaId = $(this).data('formula-id');
            var productId = $(this).data('product-id');
            var formula = $(this).data('formula');
            
            $('#formula_id').val(formulaId);
            $('#formula_product_id').val(productId);
            $('#formula_text').val(formula);
            $('.cancel-edit').show();
            
            // اسکرول به فرم
            $('html, body').animate({
                scrollTop: $('.add-formula-section').offset().top - 100
            }, 500);
        });
        
        $('.cancel-edit').on('click', function() {
            $('#formula_id').val('');
            $('#formula_product_id').val('');
            $('#formula_text').val('');
            $(this).hide();
        });
        
        // ===== مدیریت حذف فرمول =====
        $('.delete-formula').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm(wooExcelMng.strings.confirm_delete)) {
                return;
            }
            
            var $button = $(this);
            var formulaId = $button.data('formula-id');
            var $row = $button.closest('tr');
            
            $.ajax({
                url: wooExcelMng.ajax_url,
                type: 'POST',
                data: {
                    action: 'woo_excel_mng_delete_formula',
                    nonce: wooExcelMng.nonce,
                    formula_id: formulaId
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                            // بررسی وجود ردیف دیگر
                            if ($('.formulas-table-wrapper tbody tr').length === 0) {
                                $('.formulas-table-wrapper tbody').append(
                                    '<tr><td colspan="4" class="no-items">هیچ فرمولی تعریف نشده است.</td></tr>'
                                );
                            }
                        });
                    } else {
                        alert('خطا: ' + (response.data || 'خطای نامشخص'));
                    }
                },
                error: function() {
                    alert('خطا در ارتباط با سرور');
                }
            });
        });
        
        // ===== اعتبارسنجی فرم آپلود =====
        $('.upload-form').on('submit', function(e) {
            var fileInput = $(this).find('input[type="file"]');
            if (!fileInput[0].files.length) {
                e.preventDefault();
                alert('لطفاً یک فایل انتخاب کنید.');
                return false;
            }
            
            var fileName = fileInput[0].files[0].name;
            var fileExt = fileName.split('.').pop().toLowerCase();
            
            if (fileExt !== 'xlsx' && fileExt !== 'xls') {
                e.preventDefault();
                alert('فقط فایل‌های Excel (.xlsx, .xls) مجاز هستند.');
                return false;
            }
        });
        
        // ===== نمایش پیام موفقیت/خطا =====
        if ($('.notice-success, .notice-error').length) {
            setTimeout(function() {
                $('.notice-success, .notice-error').fadeOut();
            }, 5000);
        }
        
    });
    
})(jQuery);


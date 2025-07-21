/**
 * ไฟล์: assets/js/admin.js
 * JavaScript สำหรับ TPAK DQ System Admin
 */

jQuery(document).ready(function($) {
    
    console.log('TPAK DQ System JS loaded');
    
    // ตรวจสอบว่ามี tpak_dq object หรือไม่
    if (typeof tpak_dq === 'undefined') {
        console.error('TPAK Error: tpak_dq object not found');
        return;
    }
    
    // Update workflow status - ใช้ event delegation เพื่อรองรับ dynamic content
    $(document).on('click', '.tpak-update-status', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var status = button.data('status');
        var post_id = $('#post_ID').val();
        
        // Debug
        console.log('Workflow button clicked:', {
            status: status,
            post_id: post_id,
            button_text: button.text(),
            button_class: button.attr('class'),
            disabled: button.prop('disabled')
        });
        
        // ตรวจสอบว่ามี post_id หรือไม่
        if (!post_id) {
            alert('ไม่พบ Post ID');
            return;
        }
        
        // Show confirmation with comment input
        var comment = prompt('กรุณาใส่หมายเหตุ (ถ้ามี):');
        
        if (comment !== null) {
            // Store original text
            var originalText = button.text();
            
            // Show loading
            button.prop('disabled', true)
                  .removeClass('button-primary')
                  .addClass('button-disabled')
                  .text('กำลังอัพเดท...');
            
            // Debug ajax data
            var ajaxData = {
                action: 'tpak_update_workflow_status',
                nonce: tpak_dq.nonce,
                post_id: post_id,
                status: status,
                comment: comment
            };
            
            console.log('Sending AJAX request:', ajaxData);
            
            $.ajax({
                url: tpak_dq.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: ajaxData,
                success: function(response) {
                    console.log('Ajax success response:', response);
                    
                    if (response.success) {
                        // แสดงข้อความสำเร็จ
                        var successMsg = $('<div class="notice notice-success is-dismissible"><p>อัพเดทสถานะเรียบร้อยแล้ว</p></div>');
                        $('.tpak-workflow-status').before(successMsg);
                        
                        // Reload หน้าหลังจาก 1 วินาที
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        var errorMsg = response.data || 'เกิดข้อผิดพลาด';
                        alert('เกิดข้อผิดพลาด: ' + errorMsg);
                        
                        // Reset button
                        button.prop('disabled', false)
                              .addClass('button-primary')
                              .removeClass('button-disabled')
                              .text(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', {
                        xhr: xhr,
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    
                    alert('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error);
                    
                    // Reset button
                    button.prop('disabled', false)
                          .addClass('button-primary')
                          .removeClass('button-disabled')
                          .text(originalText);
                }
            });
        }
    });
    
    // Import functionality
    $('#tpak-import-btn').on('click', function() {
        var button = $(this);
        var formData = $('#tpak-import-form').serialize();
        
        // Validate form
        if (!$('#survey_id').val()) {
            alert('กรุณาระบุ Survey ID');
            return;
        }
        
        // Show progress
        $('#import-progress').show();
        $('#import-results').hide();
        button.prop('disabled', true);
        
        // Update progress message
        $('.status-message').text('กำลังเชื่อมต่อกับ LimeSurvey API...');
        
        // Start import
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData + '&action=tpak_import_data',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('.results-content').html(
                        '<div style="color: green; padding: 10px; background: #f0f8ff; border: 1px solid #d0e0f0; border-radius: 4px;">' +
                        '<strong>สำเร็จ!</strong> ' + response.data.message +
                        '</div>'
                    );
                    $('#import-results').show();
                    
                    // Update progress bar to 100%
                    $('.progress-fill').css('width', '100%');
                    $('.status-message').text('นำเข้าข้อมูลเสร็จสมบูรณ์');
                } else {
                    $('.results-content').html(
                        '<div style="color: red; padding: 10px; background: #fff0f0; border: 1px solid #ffd0d0; border-radius: 4px;">' +
                        '<strong>เกิดข้อผิดพลาด!</strong> ' + response.data +
                        '</div>'
                    );
                    $('#import-results').show();
                    $('.status-message').text('เกิดข้อผิดพลาด');
                }
            },
            error: function(xhr, status, error) {
                console.error('Import error:', error);
                $('.results-content').html(
                    '<div style="color: red; padding: 10px; background: #fff0f0; border: 1px solid #ffd0d0; border-radius: 4px;">' +
                    '<strong>เกิดข้อผิดพลาด!</strong> ' + error +
                    '</div>'
                );
                $('#import-results').show();
                $('.status-message').text('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            },
            complete: function() {
                button.prop('disabled', false);
                
                // Hide progress after 3 seconds
                setTimeout(function() {
                    $('#import-progress').fadeOut();
                    $('.progress-fill').css('width', '0%');
                }, 3000);
            }
        });
    });
    
    // Auto-save for survey data
    if ($('.tpak-editable-survey-data').length) {
        var saveTimer;
        var hasChanges = false;
        
        // Monitor changes
        $('.tpak-editable-table input, .tpak-editable-table textarea').on('input change', function() {
            hasChanges = true;
            var $field = $(this);
            
            // Add visual feedback
            $field.css('border-color', '#f0ad4e');
            
            // Clear previous timer
            clearTimeout(saveTimer);
            
            // Set new timer for auto-save (3 seconds after stop typing)
            saveTimer = setTimeout(function() {
                if (hasChanges) {
                    // Show saving indicator
                    var $notice = $('<div class="notice notice-info"><p>กำลังบันทึกอัตโนมัติ...</p></div>');
                    $('.tpak-editable-survey-data').prepend($notice);
                    
                    // Save via AJAX
                    var formData = {
                        action: 'tpak_auto_save_survey_data',
                        post_id: $('#post_ID').val(),
                        nonce: tpak_dq.nonce,
                        survey_data: {}
                    };
                    
                    // Collect all data
                    $('input[name^="tpak_survey_data"], textarea[name^="tpak_survey_data"]').each(function() {
                        var name = $(this).attr('name');
                        var key = name.match(/\[(.*?)\]/)[1];
                        formData.survey_data[key] = $(this).val();
                    });
                    
                    $.post(tpak_dq.ajax_url, formData, function(response) {
                        $notice.remove();
                        
                        if (response.success) {
                            // Show success
                            var $success = $('<div class="notice notice-success is-dismissible"><p>บันทึกอัตโนมัติเรียบร้อย</p></div>');
                            $('.tpak-editable-survey-data').prepend($success);
                            
                            // Reset field colors
                            $('.tpak-editable-table input, .tpak-editable-table textarea').css('border-color', '#ddd');
                            
                            hasChanges = false;
                            
                            // Auto-hide after 3 seconds
                            setTimeout(function() {
                                $success.fadeOut();
                            }, 3000);
                        } else {
                            alert('เกิดข้อผิดพลาดในการบันทึก: ' + response.data);
                        }
                    });
                }
            }, 3000);
        });
        
        // Warn before leaving if has unsaved changes
        $(window).on('beforeunload', function() {
            if (hasChanges) {
                return 'คุณมีการเปลี่ยนแปลงที่ยังไม่ได้บันทึก ต้องการออกจากหน้านี้หรือไม่?';
            }
        });
    }
    
    // Workflow status filter
    if ($('#workflow_status_filter').length) {
        $('#workflow_status_filter').on('change', function() {
            var status = $(this).val();
            var url = new URL(window.location);
            
            if (status) {
                url.searchParams.set('workflow_status', status);
            } else {
                url.searchParams.delete('workflow_status');
            }
            
            window.location = url.toString();
        });
    }
    
    // Date range picker enhancement
    if ($('input[type="date"]').length) {
        var today = new Date().toISOString().split('T')[0];
        var endDateInput = $('input[name="end_date"]');
        if (endDateInput.length && !endDateInput.val()) {
            endDateInput.attr('max', today).val(today);
        }
        
        var startDateInput = $('input[name="start_date"]');
        if (startDateInput.length && !startDateInput.val()) {
            var lastMonth = new Date();
            lastMonth.setMonth(lastMonth.getMonth() - 1);
            startDateInput.val(lastMonth.toISOString().split('T')[0]);
        }
    }
    
    // Confirm before bulk actions
    $('#doaction, #doaction2').on('click', function(e) {
        var action = $(this).prev('select').val();
        
        if (action === 'delete') {
            if (!confirm('คุณแน่ใจหรือไม่ที่จะลบรายการที่เลือก?')) {
                e.preventDefault();
            }
        }
    });
    
    // Toggle import type options
    $('#import_type').on('change', function() {
        if ($(this).val() === 'auto') {
            $('.auto-import-options').slideDown();
        } else {
            $('.auto-import-options').slideUp();
        }
    });
    
    // Debug function สำหรับทดสอบ workflow
    window.debugWorkflow = function() {
        console.log('=== TPAK Workflow Debug ===');
        console.log('Buttons found:', $('.tpak-update-status').length);
        console.log('Post ID:', $('#post_ID').val());
        console.log('Ajax URL:', tpak_dq.ajax_url);
        console.log('Nonce:', tpak_dq.nonce);
        
        $('.tpak-update-status').each(function(index) {
            console.log('Button ' + (index + 1) + ':', {
                text: $(this).text(),
                status: $(this).data('status'),
                classes: $(this).attr('class'),
                disabled: $(this).prop('disabled')
            });
        });
    };
    
    // Test function สำหรับทดสอบ ajax call
    window.testWorkflowAjax = function(status) {
        if (!status) {
            console.error('Please provide status. Example: testWorkflowAjax("pending_a")');
            return;
        }
        
        var testData = {
            action: 'tpak_update_workflow_status',
            nonce: tpak_dq.nonce,
            post_id: $('#post_ID').val(),
            status: status,
            comment: 'Test from console'
        };
        
        console.log('Testing with data:', testData);
        
        $.ajax({
            url: tpak_dq.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: testData,
            success: function(response) {
                console.log('Test success:', response);
            },
            error: function(xhr, status, error) {
                console.error('Test failed:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
            }
        });
    };
    
    // Log debug info on page load
    if ($('.tpak-workflow-status').length) {
        console.log('TPAK Workflow loaded. Use debugWorkflow() to see debug info.');
        console.log('Use testWorkflowAjax("pending_a") to test ajax call.');
    }
    
    // Monitor button clicks globally
    $(document).on('click', 'button', function() {
        if ($(this).hasClass('tpak-update-status')) {
            console.log('TPAK button clicked (global monitor):', {
                element: this,
                status: $(this).data('status'),
                text: $(this).text()
            });
        }
    });
});
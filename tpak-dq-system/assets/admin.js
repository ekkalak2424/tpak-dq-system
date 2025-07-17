/**
 * ไฟล์: assets/js/admin.js
 * JavaScript สำหรับ TPAK DQ System Admin
 */

jQuery(document).ready(function($) {
    
    // Update workflow status
    $('.tpak-update-status').on('click', function() {
        var button = $(this);
        var status = button.data('status');
        var post_id = $('#post_ID').val();
        
        // Show confirmation with comment input
        var comment = prompt('กรุณาใส่หมายเหตุ (ถ้ามี):');
        
        if (comment !== null) {
            // Show loading
            button.prop('disabled', true).text('กำลังอัพเดท...');
            
            $.ajax({
                url: tpak_dq.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpak_update_workflow_status',
                    nonce: tpak_dq.nonce,
                    post_id: post_id,
                    status: status,
                    comment: comment
                },
                success: function(response) {
                    if (response.success) {
                        alert('อัพเดทสถานะเรียบร้อยแล้ว');
                        location.reload();
                    } else {
                        alert('เกิดข้อผิดพลาด: ' + response.data);
                        button.prop('disabled', false).text(button.data('original-text'));
                    }
                },
                error: function() {
                    alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
                    button.prop('disabled', false).text(button.data('original-text'));
                }
            });
        }
    });
    
    // Store original button text
    $('.tpak-update-status').each(function() {
        $(this).data('original-text', $(this).text());
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
                        '<strong>สำเร็จ!</strong> ' + response.data.message
                    );
                    $('#import-results').show();
                    
                    // Update progress bar to 100%
                    $('.progress-fill').css('width', '100%');
                    $('.status-message').text('นำเข้าข้อมูลเสร็จสมบูรณ์');
                } else {
                    alert('Error: ' + response.data);
                    $('.status-message').text('เกิดข้อผิดพลาด');
                }
            },
            error: function(xhr, status, error) {
                alert('เกิดข้อผิดพลาดในการนำเข้าข้อมูล: ' + error);
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
    
    // Simulate progress bar
    function updateProgress(percent) {
        $('.progress-fill').css('width', percent + '%');
    }
    
    // Auto-hide dismissible notices
    $('.notice.is-dismissible').on('click', '.notice-dismiss', function() {
        $(this).parent().fadeOut();
    });
    
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
        $('input[name="end_date"]').attr('max', today).val(today);
        
        var lastMonth = new Date();
        lastMonth.setMonth(lastMonth.getMonth() - 1);
        $('input[name="start_date"]').val(lastMonth.toISOString().split('T')[0]);
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
    
    // AJAX test connection
    $('#test-api-connection').on('click', function() {
        var button = $(this);
        button.prop('disabled', true).text('กำลังทดสอบ...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tpak_test_api_connection',
                nonce: tpak_dq.nonce,
                api_url: $('#tpak_api_url').val(),
                username: $('#tpak_api_username').val(),
                password: $('#tpak_api_password').val()
            },
            success: function(response) {
                if (response.success) {
                    alert('เชื่อมต่อสำเร็จ!');
                } else {
                    alert('เชื่อมต่อล้มเหลว: ' + response.data);
                }
            },
            complete: function() {
                button.prop('disabled', false).text('ทดสอบการเชื่อมต่อ');
            }
        });
    });
    
    // Enhanced comment dialog
    function showCommentDialog(callback) {
        var dialog = $('<div class="tpak-modal">' +
            '<div class="tpak-modal-content">' +
            '<span class="tpak-modal-close">&times;</span>' +
            '<h3>เพิ่มหมายเหตุ</h3>' +
            '<textarea id="tpak-comment" rows="4" style="width:100%"></textarea>' +
            '<p style="margin-top:15px">' +
            '<button class="button button-primary" id="tpak-submit-comment">ตกลง</button> ' +
            '<button class="button" id="tpak-cancel-comment">ยกเลิก</button>' +
            '</p>' +
            '</div>' +
            '</div>');
        
        $('body').append(dialog);
        dialog.show();
        
        $('#tpak-submit-comment').on('click', function() {
            var comment = $('#tpak-comment').val();
            dialog.remove();
            callback(comment);
        });
        
        $('#tpak-cancel-comment, .tpak-modal-close').on('click', function() {
            dialog.remove();
            callback(null);
        });
    }
    
    // Search functionality enhancement
    var searchTimer;
    $('#tpak-quick-search').on('keyup', function() {
        clearTimeout(searchTimer);
        var query = $(this).val();
        
        searchTimer = setTimeout(function() {
            if (query.length >= 3) {
                performQuickSearch(query);
            }
        }, 500);
    });
    
    function performQuickSearch(query) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tpak_quick_search',
                nonce: tpak_dq.nonce,
                query: query
            },
            success: function(response) {
                if (response.success) {
                    displaySearchResults(response.data);
                }
            }
        });
    }
    
    function displaySearchResults(results) {
        var html = '<ul class="tpak-search-results">';
        
        if (results.length > 0) {
            $.each(results, function(i, item) {
                html += '<li><a href="' + item.url + '">' + item.title + '</a></li>';
            });
        } else {
            html += '<li>ไม่พบผลลัพธ์</li>';
        }
        
        html += '</ul>';
        $('#tpak-search-results').html(html);
    }
    
    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + S to save
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            $('#publish, #save-post').click();
        }
        
        // Ctrl/Cmd + Enter to submit form
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            var activeElement = $(document.activeElement);
            if (activeElement.is('textarea, input')) {
                activeElement.closest('form').submit();
            }
        }
    });
    
    // Auto-save draft
    if ($('#post_ID').length && $('#auto-save-enabled').is(':checked')) {
        setInterval(function() {
            var data = {
                action: 'tpak_auto_save',
                nonce: tpak_dq.nonce,
                post_id: $('#post_ID').val(),
                notes: $('#tpak_verification_notes').val()
            };
            
            $.post(ajaxurl, data, function(response) {
                if (response.success) {
                    showNotification('บันทึกร่างอัตโนมัติ', 'success');
                }
            });
        }, 60000); // Auto-save every minute
    }
    
    // Notification helper
    function showNotification(message, type) {
        var notification = $('<div class="notice notice-' + type + ' is-dismissible">' +
            '<p>' + message + '</p>' +
            '</div>');
        
        $('.wrap h1').after(notification);
        
        setTimeout(function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    // Print functionality
    $('#print-report').on('click', function() {
        window.print();
    });
    
    // Export functionality
    $('#export-data').on('click', function() {
        var format = $('#export-format').val();
        var filters = $('#filter-form').serialize();
        
        window.location.href = ajaxurl + '?action=tpak_export_data&format=' + 
                               format + '&' + filters + '&nonce=' + tpak_dq.nonce;
    });
    
    // Responsive table
    function makeTableResponsive() {
        $('.wp-list-table').each(function() {
            if ($(window).width() < 782) {
                $(this).addClass('tpak-responsive-table');
            } else {
                $(this).removeClass('tpak-responsive-table');
            }
        });
    }
    
    makeTableResponsive();
    $(window).resize(makeTableResponsive);
    
});
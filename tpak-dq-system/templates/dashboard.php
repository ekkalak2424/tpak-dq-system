<?php
/**
 * ไฟล์: templates/dashboard.php
 * Template สำหรับหน้า Dashboard
 */

// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$dashboard = new TPAK_DQ_Dashboard();
?>

<div class="wrap tpak-dashboard-wrap">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('TPAK DQ Dashboard', 'tpak-dq'); ?>
    </h1>
    
    <hr class="wp-header-end">
    
    <!-- Welcome Panel -->
    <div class="welcome-panel">
        <div class="welcome-panel-content">
            <div class="welcome-panel-header">
                <h2><?php printf(__('ยินดีต้อนรับ, %s', 'tpak-dq'), esc_html($current_user->display_name)); ?></h2>
                <p class="about-description">
                    <?php echo esc_html__('ระบบตรวจสอบคุณภาพข้อมูล TPAK Survey System', 'tpak-dq'); ?>
                </p>
            </div>
            
            <div class="welcome-panel-column-container">
                <div class="welcome-panel-column">
                    <h3><?php echo esc_html__('เริ่มต้นใช้งาน', 'tpak-dq'); ?></h3>
                    <ul>
                        <li>
                            <a href="<?php echo admin_url('edit.php?post_type=tpak_verification'); ?>" class="button button-primary">
                                <?php echo esc_html__('ดูรายการทั้งหมด', 'tpak-dq'); ?>
                            </a>
                        </li>
                        <?php if (current_user_can('manage_tpak_system')): ?>
                        <li>
                            <a href="<?php echo admin_url('edit.php?post_type=tpak_verification&page=tpak-import'); ?>">
                                <?php echo esc_html__('นำเข้าข้อมูล', 'tpak-dq'); ?>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div class="welcome-panel-column">
                    <h3><?php echo esc_html__('ทางลัด', 'tpak-dq'); ?></h3>
                    <ul>
                        <li>
                            <a href="<?php echo admin_url('edit.php?post_type=tpak_verification&workflow_status=pending_a'); ?>">
                                <?php echo esc_html__('รอ Supervisor ตรวจสอบ', 'tpak-dq'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo admin_url('edit.php?post_type=tpak_verification&workflow_status=pending_b'); ?>">
                                <?php echo esc_html__('รอ Examiner ตรวจสอบ', 'tpak-dq'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo admin_url('edit.php?post_type=tpak_verification&workflow_status=finalized'); ?>">
                                <?php echo esc_html__('อนุมัติแล้ว', 'tpak-dq'); ?>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="welcome-panel-column welcome-panel-last">
                    <h3><?php echo esc_html__('ความช่วยเหลือ', 'tpak-dq'); ?></h3>
                    <ul>
                        <li>
                            <div class="welcome-icon welcome-learn-more">
                                <?php echo esc_html__('ต้องการความช่วยเหลือ?', 'tpak-dq'); ?>
                                <a href="#"><?php echo esc_html__('ดูคู่มือการใช้งาน', 'tpak-dq'); ?></a>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics Section -->
    <div id="dashboard-widgets-wrap">
        <div id="dashboard-widgets" class="metabox-holder">
            <div id="postbox-container-1" class="postbox-container">
                <div class="meta-box-sortables">
                    <!-- Statistics Widget -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php echo esc_html__('สถิติภาพรวม', 'tpak-dq'); ?></h2>
                        </div>
                        <div class="inside">
                            <?php 
                            // This would be populated by the dashboard class
                            echo '<p>' . esc_html__('กำลังโหลดสถิติ...', 'tpak-dq') . '</p>'; 
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="postbox-container-2" class="postbox-container">
                <div class="meta-box-sortables">
                    <!-- Recent Activities Widget -->
                    <div class="postbox">
                        <div class="postbox-header">
                            <h2 class="hndle"><?php echo esc_html__('กิจกรรมล่าสุด', 'tpak-dq'); ?></h2>
                        </div>
                        <div class="inside">
                            <?php 
                            // This would be populated by the dashboard class
                            echo '<p>' . esc_html__('กำลังโหลดกิจกรรม...', 'tpak-dq') . '</p>'; 
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
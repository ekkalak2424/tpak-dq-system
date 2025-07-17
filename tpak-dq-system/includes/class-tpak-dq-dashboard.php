<?php
/**
 * ไฟล์: includes/class-tpak-dq-dashboard.php
 * จัดการหน้า Dashboard สำหรับแต่ละ Role
 */

// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_Dashboard {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_dashboard_menu'));
        add_filter('manage_tpak_verification_posts_columns', array($this, 'add_custom_columns'));
        add_action('manage_tpak_verification_posts_custom_column', array($this, 'render_custom_columns'), 10, 2);
        add_filter('manage_edit-tpak_verification_sortable_columns', array($this, 'make_columns_sortable'));
        add_action('pre_get_posts', array($this, 'filter_posts_by_role'));
        add_filter('views_edit-tpak_verification', array($this, 'add_status_filters'));
    }
    
    public function add_dashboard_menu() {
        add_submenu_page(
            'edit.php?post_type=tpak_verification',
            'Dashboard',
            'Dashboard',
            'read', // เปลี่ยนจาก 'view_tpak_surveys' เป็น 'read' เพื่อให้ทุกคนเห็น
            'tpak-dashboard',
            array($this, 'render_dashboard')
        );
    }
    
    public function render_dashboard() {
        $current_user = wp_get_current_user();
        $user_role = $this->get_user_tpak_role();
        ?>
        <div class="wrap">
            <h1>TPAK DQ Dashboard</h1>
            
            <div class="welcome-panel">
                <div class="welcome-panel-content">
                    <h2>ยินดีต้อนรับ, <?php echo esc_html($current_user->display_name); ?></h2>
                    <p class="about-description">
                        Role ของคุณ: <strong><?php echo $this->get_role_display_name($user_role); ?></strong>
                    </p>
                </div>
            </div>
            
            <?php $this->render_statistics($user_role); ?>
            
            <?php $this->render_recent_activities($user_role); ?>
            
            <?php if (in_array($user_role, array('supervisor', 'examiner', 'admin'))): ?>
                <?php $this->render_pending_reviews($user_role); ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function render_statistics($user_role) {
        // Get statistics based on role
        $stats = $this->get_statistics_by_role($user_role);
        ?>
        <div class="tpak-statistics">
            <h3>สถิติภาพรวม</h3>
            <div class="stat-boxes">
                <?php foreach ($stats as $stat): ?>
                <div class="stat-box">
                    <div class="stat-number"><?php echo intval($stat['count']); ?></div>
                    <div class="stat-label"><?php echo esc_html($stat['label']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    private function render_recent_activities($user_role) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tpak_dq_audit';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
        
        if (!$table_exists) {
            ?>
            <div class="tpak-recent-activities">
                <h3>กิจกรรมล่าสุด</h3>
                <p>ยังไม่มีกิจกรรม</p>
            </div>
            <?php
            return;
        }
        
        // Get recent activities
        if ($user_role === 'interviewer') {
            $user_id = get_current_user_id();
            $where = $wpdb->prepare(" WHERE user_id = %d", $user_id);
        } else {
            $where = "";
        }
        
        $activities = $wpdb->get_results(
            "SELECT * FROM $table $where ORDER BY created_at DESC LIMIT 10"
        );
        
        ?>
        <div class="tpak-recent-activities">
            <h3>กิจกรรมล่าสุด</h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>วันที่</th>
                        <th>ผู้ดำเนินการ</th>
                        <th>การดำเนินการ</th>
                        <th>รายการ</th>
                        <th>สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($activities): ?>
                        <?php foreach ($activities as $activity): ?>
                        <?php 
                        $user = get_userdata($activity->user_id);
                        $post = get_post($activity->post_id);
                        ?>
                        <tr>
                            <td><?php echo date_i18n('d/m/Y H:i', strtotime($activity->created_at)); ?></td>
                            <td><?php echo $user ? esc_html($user->display_name) : 'Unknown'; ?></td>
                            <td><?php echo esc_html($activity->action); ?></td>
                            <td>
                                <?php if ($post): ?>
                                    <a href="<?php echo get_edit_post_link($post->ID); ?>">
                                        <?php echo esc_html($post->post_title); ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo $this->get_status_label($activity->new_status); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">ไม่มีกิจกรรม</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    private function render_pending_reviews($user_role) {
        $args = array(
            'post_type' => 'tpak_verification',
            'posts_per_page' => 10,
            'meta_query' => array()
        );
        
        // Filter by role
        if ($user_role === 'supervisor') {
            $args['meta_query'][] = array(
                'key' => '_tpak_workflow_status',
                'value' => 'pending_a',
                'compare' => '='
            );
        } elseif ($user_role === 'examiner') {
            $args['meta_query'][] = array(
                'key' => '_tpak_workflow_status',
                'value' => 'pending_b',
                'compare' => '='
            );
        }
        
        $query = new WP_Query($args);
        ?>
        <div class="tpak-pending-reviews">
            <h3>รอการตรวจสอบ</h3>
            <?php if ($query->have_posts()): ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>ชื่อ</th>
                            <th>ผู้ส่ง</th>
                            <th>วันที่ส่ง</th>
                            <th>การดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($query->have_posts()): $query->the_post(); ?>
                        <tr>
                            <td>
                                <a href="<?php echo get_edit_post_link(); ?>">
                                    <?php the_title(); ?>
                                </a>
                            </td>
                            <td><?php the_author(); ?></td>
                            <td><?php echo get_the_date('d/m/Y H:i'); ?></td>
                            <td>
                                <a href="<?php echo get_edit_post_link(); ?>" class="button button-small">
                                    ตรวจสอบ
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>ไม่มีรายการรอตรวจสอบ</p>
            <?php endif; ?>
            <?php wp_reset_postdata(); ?>
        </div>
        <?php
    }
    
    public function add_custom_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $title) {
            if ($key === 'title') {
                $new_columns[$key] = $title;
                $new_columns['survey_id'] = 'Survey ID';
                $new_columns['workflow_status'] = 'สถานะ';
                $new_columns['assigned_to'] = 'มอบหมายให้';
            } else {
                $new_columns[$key] = $title;
            }
        }
        
        return $new_columns;
    }
    
    public function render_custom_columns($column, $post_id) {
        switch ($column) {
            case 'survey_id':
                echo esc_html(get_post_meta($post_id, '_tpak_survey_id', true));
                break;
                
            case 'workflow_status':
                $status = get_post_meta($post_id, '_tpak_workflow_status', true);
                echo '<span class="status-' . esc_attr($status) . '">' . 
                     $this->get_status_label($status) . '</span>';
                break;
                
            case 'assigned_to':
                $assigned = get_post_meta($post_id, '_tpak_assigned_to', true);
                if ($assigned) {
                    $user = get_userdata($assigned);
                    echo $user ? esc_html($user->display_name) : '-';
                } else {
                    echo '-';
                }
                break;
        }
    }
    
    public function make_columns_sortable($columns) {
        $columns['survey_id'] = 'survey_id';
        $columns['workflow_status'] = 'workflow_status';
        return $columns;
    }
    
    public function filter_posts_by_role($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        if ($query->get('post_type') !== 'tpak_verification') {
            return;
        }
        
        $user_role = $this->get_user_tpak_role();
        
        // Filter based on role
        if ($user_role === 'interviewer') {
            $query->set('author', get_current_user_id());
        }
        
        // Handle custom orderby
        $orderby = $query->get('orderby');
        
        if ($orderby === 'survey_id') {
            $query->set('meta_key', '_tpak_survey_id');
            $query->set('orderby', 'meta_value');
        } elseif ($orderby === 'workflow_status') {
            $query->set('meta_key', '_tpak_workflow_status');
            $query->set('orderby', 'meta_value');
        }
        
        // Handle workflow status filter
        if (isset($_GET['workflow_status'])) {
            $query->set('meta_key', '_tpak_workflow_status');
            $query->set('meta_value', sanitize_text_field($_GET['workflow_status']));
        }
    }
    
    public function add_status_filters($views) {
        $statuses = array(
            '' => 'ทั้งหมด',
            'pending_a' => 'รอ Supervisor',
            'pending_b' => 'รอ Examiner',
            'rejected_by_b' => 'ถูกส่งกลับโดย Supervisor',
            'rejected_by_c' => 'ถูกส่งกลับโดย Examiner',
            'finalized' => 'อนุมัติแล้ว'
        );
        
        $current_status = isset($_GET['workflow_status']) ? $_GET['workflow_status'] : '';
        
        foreach ($statuses as $status => $label) {
            $class = ($current_status === $status) ? ' class="current"' : '';
            $url = add_query_arg('workflow_status', $status);
            
            $count = $this->get_status_count($status);
            
            $views['status_' . $status] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url($url),
                $class,
                esc_html($label),
                $count
            );
        }
        
        return $views;
    }
    
    private function get_statistics_by_role($user_role) {
        $stats = array();
        
        switch ($user_role) {
            case 'interviewer':
                $stats[] = array(
                    'count' => $this->get_user_post_count('', get_current_user_id()),
                    'label' => 'รายการทั้งหมด'
                );
                $stats[] = array(
                    'count' => $this->get_user_post_count('pending_a', get_current_user_id()),
                    'label' => 'รอตรวจสอบ'
                );
                $stats[] = array(
                    'count' => $this->get_user_post_count('finalized', get_current_user_id()),
                    'label' => 'อนุมัติแล้ว'
                );
                break;
                
            case 'supervisor':
                $stats[] = array(
                    'count' => $this->get_status_count('pending_a'),
                    'label' => 'รอตรวจสอบ'
                );
                $stats[] = array(
                    'count' => $this->get_status_count('pending_b'),
                    'label' => 'ส่งต่อ Examiner แล้ว'
                );
                $stats[] = array(
                    'count' => $this->get_status_count('rejected_by_b'),
                    'label' => 'ส่งกลับแก้ไข'
                );
                break;
                
            case 'examiner':
                $stats[] = array(
                    'count' => $this->get_status_count('pending_b'),
                    'label' => 'รอตรวจสอบ'
                );
                $stats[] = array(
                    'count' => $this->get_status_count('finalized'),
                    'label' => 'อนุมัติแล้ว'
                );
                $stats[] = array(
                    'count' => $this->get_status_count('rejected_by_c'),
                    'label' => 'ส่งกลับแก้ไข'
                );
                break;
                
            case 'admin':
            default:
                $count_posts = wp_count_posts('tpak_verification');
                $total = 0;
                if (isset($count_posts->publish)) {
                    $total = $count_posts->publish;
                }
                
                $stats[] = array(
                    'count' => $total,
                    'label' => 'รายการทั้งหมด'
                );
                $stats[] = array(
                    'count' => $this->get_status_count('finalized'),
                    'label' => 'อนุมัติแล้ว'
                );
                $stats[] = array(
                    'count' => $this->get_pending_count(),
                    'label' => 'รอดำเนินการ'
                );
                break;
        }
        
        return $stats;
    }
    
    private function get_user_post_count($status = '', $user_id = 0) {
        $args = array(
            'post_type' => 'tpak_verification',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        
        if ($user_id) {
            $args['author'] = $user_id;
        }
        
        if ($status) {
            $args['meta_query'] = array(
                array(
                    'key' => '_tpak_workflow_status',
                    'value' => $status,
                    'compare' => '='
                )
            );
        }
        
        $query = new WP_Query($args);
        return $query->found_posts;
    }
    
    private function get_status_count($status) {
        return $this->get_user_post_count($status);
    }
    
    private function get_pending_count() {
        $pending_statuses = array('', 'pending_a', 'pending_b', 'rejected_by_b', 'rejected_by_c');
        $total = 0;
        
        foreach ($pending_statuses as $status) {
            $total += $this->get_status_count($status);
        }
        
        return $total;
    }
    
    private function get_user_tpak_role() {
        $user = wp_get_current_user();
        
        if (in_array('tpak_examiner', $user->roles)) {
            return 'examiner';
        } elseif (in_array('tpak_supervisor', $user->roles)) {
            return 'supervisor';
        } elseif (in_array('tpak_interviewer', $user->roles)) {
            return 'interviewer';
        } elseif (in_array('administrator', $user->roles)) {
            return 'admin';
        }
        
        return 'admin'; // default to admin if no role found
    }
    
    private function get_role_display_name($role) {
        $names = array(
            'interviewer' => 'Interviewer (Role A)',
            'supervisor' => 'Supervisor (Role B)',
            'examiner' => 'Examiner (Role C)',
            'admin' => 'Administrator'
        );
        
        return isset($names[$role]) ? $names[$role] : 'Unknown';
    }
    
    private function get_status_label($status) {
        $labels = array(
            '' => 'รอดำเนินการ',
            'pending_a' => 'รอ Supervisor ตรวจสอบ',
            'pending_b' => 'รอ Examiner ตรวจสอบ',
            'pending_c' => 'รอการอนุมัติ',
            'rejected_by_b' => 'ถูกส่งกลับโดย Supervisor',
            'rejected_by_c' => 'ถูกส่งกลับโดย Examiner',
            'finalized' => 'อนุมัติแล้ว'
        );
        
        return isset($labels[$status]) ? $labels[$status] : 'ไม่ทราบสถานะ';
    }
}
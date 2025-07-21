<?php
/**
 * เพิ่ม code นี้ในไฟล์ tpak-dq-system.php หรือสร้างไฟล์ใหม่
 * includes/class-tpak-dq-demo-users.php
 */

class TPAK_DQ_Demo_Users {
    
    /**
     * สร้าง Demo Users สำหรับทดสอบระบบ
     */
    public static function create_demo_users() {
        
        // Array ของ users ที่จะสร้าง
        $demo_users = array(
            array(
                'user_login' => 'interviewer1',
                'user_pass' => 'interviewer123',
                'user_email' => 'interviewer1@tpak.test',
                'display_name' => 'นาย สมชาย ทดสอบ',
                'first_name' => 'สมชาย',
                'last_name' => 'ทดสอบ',
                'role' => 'tpak_interviewer',
                'description' => 'Interviewer (Role A) - ผู้สัมภาษณ์'
            ),
            array(
                'user_login' => 'interviewer2',
                'user_pass' => 'interviewer123',
                'user_email' => 'interviewer2@tpak.test',
                'display_name' => 'นาง สมหญิง ทดลอง',
                'first_name' => 'สมหญิง',
                'last_name' => 'ทดลอง',
                'role' => 'tpak_interviewer',
                'description' => 'Interviewer (Role A) - ผู้สัมภาษณ์'
            ),
            array(
                'user_login' => 'supervisor1',
                'user_pass' => 'supervisor123',
                'user_email' => 'supervisor1@tpak.test',
                'display_name' => 'นาย นิเทศ ตรวจสอบ',
                'first_name' => 'นิเทศ',
                'last_name' => 'ตรวจสอบ',
                'role' => 'tpak_supervisor',
                'description' => 'Supervisor (Role B) - ผู้ตรวจสอบ'
            ),
            array(
                'user_login' => 'examiner1',
                'user_pass' => 'examiner123',
                'user_email' => 'examiner1@tpak.test',
                'display_name' => 'นาย อนุมัติ ขั้นสุดท้าย',
                'first_name' => 'อนุมัติ',
                'last_name' => 'ขั้นสุดท้าย',
                'role' => 'tpak_examiner',
                'description' => 'Examiner (Role C) - ผู้อนุมัติ'
            )
        );
        
        $created_users = array();
        
        foreach ($demo_users as $user_data) {
            // ตรวจสอบว่า user มีอยู่แล้วหรือไม่
            if (username_exists($user_data['user_login']) || email_exists($user_data['user_email'])) {
                continue;
            }
            
            // สร้าง user ใหม่
            $user_id = wp_create_user(
                $user_data['user_login'],
                $user_data['user_pass'],
                $user_data['user_email']
            );
            
            if (!is_wp_error($user_id)) {
                // Update user meta
                wp_update_user(array(
                    'ID' => $user_id,
                    'display_name' => $user_data['display_name'],
                    'first_name' => $user_data['first_name'],
                    'last_name' => $user_data['last_name'],
                    'description' => $user_data['description']
                ));
                
                // Set user role
                $user = new WP_User($user_id);
                $user->set_role($user_data['role']);
                
                $created_users[] = array(
                    'username' => $user_data['user_login'],
                    'password' => $user_data['user_pass'],
                    'role' => $user_data['role'],
                    'name' => $user_data['display_name']
                );
            }
        }
        
        return $created_users;
    }
    
    /**
     * แสดงข้อมูล Demo Users ใน Admin Notice
     */
    public static function show_demo_users_notice() {
        // ตรวจสอบว่าเป็น admin และอยู่ในหน้า users
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // ตรวจสอบว่ามี demo users หรือยัง
        $interviewer_exists = username_exists('interviewer1');
        
        if (!$interviewer_exists) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><strong>TPAK DQ System:</strong> ยังไม่มี Demo Users สำหรับทดสอบระบบ</p>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=tpak-create-demo-users'); ?>" 
                       class="button button-primary">
                        สร้าง Demo Users
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * หน้าสำหรับสร้าง Demo Users
     */
    public static function render_create_users_page() {
        if (!current_user_can('manage_options')) {
            wp_die('คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
        }
        
        $message = '';
        
        // ตรวจสอบว่ามีการ submit form หรือไม่
        if (isset($_POST['create_demo_users']) && wp_verify_nonce($_POST['_wpnonce'], 'create_demo_users')) {
            $created_users = self::create_demo_users();
            
            if (!empty($created_users)) {
                $message = '<div class="notice notice-success"><p><strong>สร้าง Demo Users สำเร็จ!</strong></p>';
                $message .= '<table class="widefat" style="max-width: 600px; margin-top: 10px;">';
                $message .= '<thead><tr><th>Username</th><th>Password</th><th>Role</th><th>ชื่อ</th></tr></thead>';
                $message .= '<tbody>';
                
                foreach ($created_users as $user) {
                    $message .= '<tr>';
                    $message .= '<td>' . esc_html($user['username']) . '</td>';
                    $message .= '<td>' . esc_html($user['password']) . '</td>';
                    $message .= '<td>' . esc_html($user['role']) . '</td>';
                    $message .= '<td>' . esc_html($user['name']) . '</td>';
                    $message .= '</tr>';
                }
                
                $message .= '</tbody></table>';
                $message .= '<p class="description" style="margin-top: 10px;">⚠️ กรุณาบันทึกข้อมูล username และ password ไว้ใช้งาน</p>';
                $message .= '</div>';
            } else {
                $message = '<div class="notice notice-info"><p>Demo Users ถูกสร้างไว้แล้ว</p></div>';
            }
        }
        
        ?>
        <div class="wrap">
            <h1>สร้าง Demo Users - TPAK DQ System</h1>
            
            <?php echo $message; ?>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>รายละเอียด Demo Users ที่จะสร้าง</h2>
                
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>ชื่อ</th>
                            <th>หน้าที่</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Interviewer</strong></td>
                            <td>interviewer1</td>
                            <td>interviewer1@tpak.test</td>
                            <td>นาย สมชาย ทดสอบ</td>
                            <td>ผู้สัมภาษณ์ - นำเข้าข้อมูลและส่งตรวจสอบ</td>
                        </tr>
                        <tr class="alternate">
                            <td><strong>Interviewer</strong></td>
                            <td>interviewer2</td>
                            <td>interviewer2@tpak.test</td>
                            <td>นาง สมหญิง ทดลอง</td>
                            <td>ผู้สัมภาษณ์ - นำเข้าข้อมูลและส่งตรวจสอบ</td>
                        </tr>
                        <tr>
                            <td><strong>Supervisor</strong></td>
                            <td>supervisor1</td>
                            <td>supervisor1@tpak.test</td>
                            <td>นาย นิเทศ ตรวจสอบ</td>
                            <td>ผู้ตรวจสอบ - ตรวจสอบและส่งต่อ/ส่งกลับ</td>
                        </tr>
                        <tr class="alternate">
                            <td><strong>Examiner</strong></td>
                            <td>examiner1</td>
                            <td>examiner1@tpak.test</td>
                            <td>นาย อนุมัติ ขั้นสุดท้าย</td>
                            <td>ผู้อนุมัติ - ตรวจสอบขั้นสุดท้ายและอนุมัติ</td>
                        </tr>
                    </tbody>
                </table>
                
                <form method="post" style="margin-top: 20px;">
                    <?php wp_nonce_field('create_demo_users'); ?>
                    <input type="hidden" name="create_demo_users" value="1">
                    
                    <?php 
                    $interviewer_exists = username_exists('interviewer1');
                    if ($interviewer_exists) {
                        echo '<p class="description">⚠️ Demo Users ถูกสร้างไว้แล้ว หากต้องการสร้างใหม่ กรุณาลบ users เดิมก่อน</p>';
                    }
                    ?>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary" 
                                <?php echo $interviewer_exists ? 'disabled' : ''; ?>>
                            สร้าง Demo Users ทั้งหมด
                        </button>
                        
                        <a href="<?php echo admin_url('users.php'); ?>" class="button">
                            ดู Users ทั้งหมด
                        </a>
                    </p>
                </form>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>วิธีใช้งาน</h2>
                <ol>
                    <li>คลิกปุ่ม "สร้าง Demo Users ทั้งหมด" เพื่อสร้าง users ทดสอบ</li>
                    <li>บันทึก username และ password ที่แสดงไว้ใช้งาน</li>
                    <li>Login ด้วย user แต่ละ role เพื่อทดสอบระบบ</li>
                    <li>ทดสอบ workflow ตามลำดับ: Interviewer → Supervisor → Examiner</li>
                </ol>
                
                <h3>สิทธิ์ของแต่ละ Role:</h3>
                <ul>
                    <li><strong>Interviewer:</strong> นำเข้าข้อมูล, ดูเฉพาะข้อมูลของตนเอง, ส่งตรวจสอบ</li>
                    <li><strong>Supervisor:</strong> ตรวจสอบข้อมูลจาก Interviewer, ส่งต่อหรือส่งกลับ</li>
                    <li><strong>Examiner:</strong> ตรวจสอบขั้นสุดท้าย, อนุมัติหรือส่งกลับ</li>
                </ul>
            </div>
        </div>
        <?php
    }
}

// Hook เพื่อแสดง notice
add_action('admin_notices', array('TPAK_DQ_Demo_Users', 'show_demo_users_notice'));

// เพิ่มหน้า admin menu สำหรับสร้าง demo users
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=tpak_verification',
        'สร้าง Demo Users',
        'สร้าง Demo Users',
        'manage_options',
        'tpak-create-demo-users',
        array('TPAK_DQ_Demo_Users', 'render_create_users_page')
    );
});
<?php
/**
 * ไฟล์: includes/class-tpak-dq-roles.php
 * จัดการ User Roles และ Permissions
 */

// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_Roles {
    
    public static function create_roles() {
        // Remove existing roles first
        remove_role('tpak_interviewer');
        remove_role('tpak_supervisor');
        remove_role('tpak_examiner');
        
        // Role A - Interviewer
        add_role('tpak_interviewer', 'Interviewer (Role A)', array(
            'read' => true,
            'upload_files' => true,
            
            // WordPress standard capabilities for posts
            'edit_posts' => true, // สำคัญ! ต้องมีเพื่อเข้า admin
            'edit_published_posts' => true,
            'publish_posts' => true,
            'delete_posts' => false,
            'delete_published_posts' => false,
            
            // Custom capabilities
            'view_tpak_surveys' => true,
            'edit_own_tpak_surveys' => true,
            'submit_tpak_surveys' => true,
            'import_tpak_surveys' => true, // เพิ่มสิทธิ์ import
        ));
        
        // Role B - Supervisor
        add_role('tpak_supervisor', 'Supervisor (Role B)', array(
            'read' => true,
            'upload_files' => true,
            
            // WordPress standard capabilities
            'edit_posts' => true,
            'edit_others_posts' => true,
            'edit_published_posts' => true,
            'publish_posts' => true,
            'delete_posts' => false,
            'delete_others_posts' => false,
            
            // Inherit from Interviewer
            'view_tpak_surveys' => true,
            'edit_own_tpak_surveys' => true,
            'submit_tpak_surveys' => true,
            
            // Additional capabilities
            'review_tpak_surveys' => true,
            'approve_tpak_surveys' => true,
            'edit_others_tpak_surveys' => true,
        ));
        
        // Role C - Examiner
        add_role('tpak_examiner', 'Examiner (Role C)', array(
            'read' => true,
            'upload_files' => true,
            
            // WordPress standard capabilities
            'edit_posts' => true,
            'edit_others_posts' => true,
            'edit_published_posts' => true,
            'publish_posts' => true,
            'delete_posts' => false,
            'delete_others_posts' => false,
            
            // Inherit from Supervisor
            'view_tpak_surveys' => true,
            'review_tpak_surveys' => true,
            'approve_tpak_surveys' => true,
            'edit_others_tpak_surveys' => true,
            
            // Additional capabilities
            'final_review_tpak_surveys' => true,
            'export_tpak_surveys' => true,
            'view_all_tpak_data' => true,
        ));
        
        // Add capabilities to Administrator
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('view_tpak_surveys');
            $admin->add_cap('edit_own_tpak_surveys');
            $admin->add_cap('edit_others_tpak_surveys');
            $admin->add_cap('submit_tpak_surveys');
            $admin->add_cap('review_tpak_surveys');
            $admin->add_cap('approve_tpak_surveys');
            $admin->add_cap('final_review_tpak_surveys');
            $admin->add_cap('export_tpak_surveys');
            $admin->add_cap('view_all_tpak_data');
            $admin->add_cap('manage_tpak_system');
            $admin->add_cap('import_tpak_surveys');
        }
    }
    
    /**
     * Helper function to check if user has TPAK role
     */
    public static function user_has_tpak_role($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        $tpak_roles = array('tpak_interviewer', 'tpak_supervisor', 'tpak_examiner', 'administrator');
        
        foreach ($tpak_roles as $role) {
            if (in_array($role, $user->roles)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get user's TPAK role
     */
    public static function get_user_tpak_role($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return '';
        }
        
        if (in_array('administrator', $user->roles)) {
            return 'administrator';
        } elseif (in_array('tpak_examiner', $user->roles)) {
            return 'tpak_examiner';
        } elseif (in_array('tpak_supervisor', $user->roles)) {
            return 'tpak_supervisor';
        } elseif (in_array('tpak_interviewer', $user->roles)) {
            return 'tpak_interviewer';
        }
        
        return '';
    }
}
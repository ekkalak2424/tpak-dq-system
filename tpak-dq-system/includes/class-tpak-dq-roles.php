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
        add_role('tpak_interviewer', 'Interviewer', array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            'publish_posts' => false,
            'upload_files' => true,
            
            // Custom capabilities
            'view_tpak_surveys' => true,
            'edit_own_tpak_surveys' => true,
            'submit_tpak_surveys' => true,
        ));
        
        // Role B - Supervisor
        add_role('tpak_supervisor', 'Supervisor', array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            'publish_posts' => false,
            'upload_files' => true,
            
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
        add_role('tpak_examiner', 'Examiner', array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            'publish_posts' => false,
            'upload_files' => true,
            
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
        }
    }
}
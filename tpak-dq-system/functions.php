// Debug: ดู capabilities ของ user ปัจจุบัน
add_action('admin_notices', function() {
    $user = wp_get_current_user();
    echo '<div class="notice notice-info"><p>Current user roles: ' . implode(', ', $user->roles) . '</p></div>';
    echo '<div class="notice notice-info"><p>Capabilities: ' . implode(', ', array_keys($user->allcaps)) . '</p></div>';
});
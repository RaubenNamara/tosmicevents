<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
if (is_post()) {
    csrf_check();
    $_SESSION = [];
    session_regenerate_id(true);
    flash('success', 'You have been signed out.');
}
redirect('admin/login.php');

<?php
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD']==='POST' && (isset($_SESSION['teacher_id']) || !empty($_SESSION['admin_logged_in']))) {
    $token=$_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'],$token)) {
        http_response_code(403);header('Content-Type: application/json');echo json_encode(['success'=>false,'message'=>'Invalid CSRF token']);exit;
    }
}

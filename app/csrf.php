<?php
// csrf.php
// 用于后台表单CSRF防护
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../app/secure_random.php';

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = secure_random_str(32);
    }
    return $_SESSION['csrf_token'];
}

function check_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        if (function_exists('logAction')) {
            $user = $_SESSION['user'] ?? 'unknown';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            logAction($user, $ip, 'CSRF校验失败');
        }
        // 判断请求是否为AJAX或JSON或上传接口
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $isJson = (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        $isUpload = isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], 'upload.php') !== false;
        if ($isAjax || $isJson || $isUpload || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'CSRF校验失败']);
        } else {
            echo '<div style="color:red;font-weight:bold;">CSRF校验失败</div>';
        }
        exit;
    }
}

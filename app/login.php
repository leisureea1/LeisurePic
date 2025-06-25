<?php
require_once __DIR__ . '/../app/error_log.php';
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/csrf.php'; // 引入CSRF

// 登录失败次数限制（基于用户名+IP，文件存储）
$login_attempts_dir = __DIR__ . '/../data/login_attempts';
if (!is_dir($login_attempts_dir)) {
    mkdir($login_attempts_dir, 0777, true);
}
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$attempt_key = md5(strtolower(trim($_POST['username'] ?? '')) . '_' . $ip);
$attempt_file = $login_attempts_dir . '/' . $attempt_key . '.json';
$fail_count = 0;
$fail_time = 0;
if (file_exists($attempt_file)) {
    $data = json_decode(file_get_contents($attempt_file), true);
    $fail_count = $data['count'] ?? 0;
    $fail_time = $data['time'] ?? 0;
}
if ($fail_count >= 5 && time() - $fail_time < 300) {
    http_response_code(429);
    echo json_encode(['error' => '登录失败次数过多，请5分钟后再试']);
    exit;
}

// 只支持表单方式
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if (!$username || !$password) {
    http_response_code(400);
    echo json_encode(['error' => '用户名和密码不能为空']);
    exit;
}

$users = require __DIR__ . '/../config/users.php';

if (!isset($users[$username]) || !password_verify($password, $users[$username])) {
    $fail_count++;
    $fail_time = time();
    file_put_contents($attempt_file, json_encode(['count' => $fail_count, 'time' => $fail_time]));
    http_response_code(401);
    echo json_encode(['error' => '用户名或密码错误']);
    exit;
}

// 登录成功，重置失败次数，防止会话固定
if (file_exists($attempt_file)) unlink($attempt_file);
session_regenerate_id(true);
$_SESSION['user'] = $username;
$csrf_token = csrf_token();

echo json_encode(['success' => true, 'message' => '登录成功', 'csrf_token' => $csrf_token]);
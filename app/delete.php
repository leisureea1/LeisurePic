<?php
require_once __DIR__ . '/../app/error_log.php';
require_once __DIR__ . '/../app/csrf.php';
session_start();
// 会话超时与安全校验
$session_timeout = 900; // 15分钟
if (empty($_SESSION['user'])) {
    header('Location: /index.php');
    exit;
}
if (!isset($_SESSION['last_active'])) {
    $_SESSION['last_active'] = time();
} elseif (time() - $_SESSION['last_active'] > $session_timeout) {
    session_unset();
    session_destroy();
    header('Location: /index.php?timeout=1');
    exit;
} else {
    $_SESSION['last_active'] = time();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/core.php';

header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => '请先登录']);
    exit;
}

$user = $_SESSION['user'];
$filename = $_POST['filename'] ?? '';

if (!$filename) {
    error_log('删除失败：文件名为空，操作用户：' . ($_SESSION['user'] ?? '未知'));
    header('Location: image_manage.php?error=操作失败');
    exit;
}

$uploadDir = realpath(__DIR__ . '/../public/uploads/');
$filePath = realpath($uploadDir . '/' . $filename);

// 安全检查：防止目录穿越
if (!$filePath || strpos($filePath, $uploadDir) !== 0) {
    http_response_code(400);
    echo json_encode(['error' => '非法文件路径']);
    exit;
}

// TODO: 如果需要，可以检查文件归属用户（这里暂不实现用户绑定）

if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => '文件不存在']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: image_manage.php');
    exit;
}
check_csrf_token($_POST['csrf_token'] ?? '');

// 记录日志
logAction($user, $_SERVER['REMOTE_ADDR'], "删除文件: $filename");

echo json_encode(['success' => true, 'message' => '删除成功']);
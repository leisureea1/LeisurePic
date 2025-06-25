<?php
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
require_once __DIR__ . '/../app/error_log.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/core.php';

$uploadDir = realpath(__DIR__ . '/../public/uploads/');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: image_manage.php');
    exit;
}
check_csrf_token($_POST['csrf_token'] ?? '');

$absPath = $_POST['abs_path'] ?? [];

// 批量删除
if (isset($_POST['batch']) && !empty($_POST['filenames']) && is_array($_POST['filenames'])) {
    $deleted = 0;
    foreach ($_POST['filenames'] as $filename) {
        $filename = trim($filename, "/\\");
        // 支持 abs_path 批量删除
        if (is_array($absPath) && isset($absPath[$filename]) && file_exists($absPath[$filename])) {
            $filePath = $absPath[$filename];
        } else {
            // 兼容老逻辑
            $filePath = realpath(__DIR__ . '/../public/uploads/' . $filename);
        }
        if ($filePath && strpos($filePath, realpath(__DIR__ . '/../public/uploads/')) === 0 && file_exists($filePath)) {
            if (unlink($filePath)) {
                $deleted++;
                logAction($_SESSION['user'], $_SERVER['REMOTE_ADDR'], "后台批量删除文件: $filename");
            }
        }
    }
    header('Location: image_manage.php?success=已批量删除' . $deleted . '张图片');
    exit;
}

// 单个删除
$filename = $_POST['filename'] ?? '';
if (!$filename) {
    error_log('删除失败：文件名为空，操作用户：' . ($_SESSION['user'] ?? '未知'));
    header('Location: image_manage.php?error=操作失败');
    exit;
}

// 统一单文件删除逻辑：只允许 public/uploads 下的文件
$filePath = realpath(__DIR__ . '/../public/uploads/' . $filename);
if (!$filePath || strpos($filePath, $uploadDir) !== 0) {
    error_log('删除失败：非法文件路径 ' . $filename . '，操作用户：' . ($_SESSION['user'] ?? '未知'));
    header('Location: image_manage.php?error=操作失败');
    exit;
}
if (!file_exists($filePath)) {
    error_log('删除失败：文件不存在 ' . $filePath . '，操作用户：' . ($_SESSION['user'] ?? '未知'));
    header('Location: image_manage.php?error=操作失败');
    exit;
}
if (!unlink($filePath)) {
    error_log('删除失败：无法删除文件 ' . $filePath . '，操作用户：' . ($_SESSION['user'] ?? '未知'));
    header('Location: image_manage.php?error=操作失败');
    exit;
}
logAction($_SESSION['user'], $_SERVER['REMOTE_ADDR'], "后台删除文件: $filename");
header('Location: image_manage.php?success=删除成功');


if (!unlink($filePath)) {
    error_log('删除失败：无法删除文件 ' . $filePath . '，操作用户：' . ($_SESSION['user'] ?? '未知'));
    header('Location: image_manage.php?error=操作失败');
    exit;
}
logAction($_SESSION['user'], $_SERVER['REMOTE_ADDR'], "后台删除文件: $filename");
header('Location: image_manage.php?success=删除成功');
exit;
?>

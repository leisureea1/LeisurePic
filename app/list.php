<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/core.php';
require_once __DIR__ . '/../app/error_log.php';

header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => '请先登录']);
    exit;
}

http_response_code(403);
echo json_encode(['error' => '请通过后台管理查看图片列表']);
exit;
<?php
require_once __DIR__ . '/../app/error_log.php';
session_start();
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../app/core.php';

    $config = include __DIR__ . '/../config/config.php';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // 检查IP是否在黑名单
    $isBlacklisted = false;
    if (isset($config['ip_blacklist']) && is_array($config['ip_blacklist'])) {
        $isBlacklisted = in_array($ip, $config['ip_blacklist']);
    }

    // 统计当前IP今日上传次数
    $currentCount = 0;
    $maxUploadCount = isset($config['upload_limit_per_day']) ? intval($config['upload_limit_per_day']) : 0;
    $logFile = __DIR__ . '/../log/upload_log.json';
    if (file_exists($logFile)) {
        $logs = json_decode(file_get_contents($logFile), true) ?: [];
        $today = date('Y-m-d');
        foreach ($logs as $log) {
            if ($log['ip'] === $ip && strpos($log['time'], $today) === 0) {
                $currentCount++;
            }
        }
    }

    $allowed = true;
    if ($isBlacklisted) {
        $allowed = false;
    } elseif ($maxUploadCount > 0 && $currentCount >= $maxUploadCount) {
        $allowed = false;
    }

    echo json_encode(['allowed' => $allowed, 'is_blacklisted' => $isBlacklisted]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['allowed' => false, 'error' => '服务器异常: ' . $e->getMessage()]);
}
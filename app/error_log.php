<?php
// 生产环境建议关闭错误显示，记录到日志
ini_set('display_errors', 0);
ini_set('log_errors', 1);
$logDir = dirname(__DIR__) . '/log';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
ini_set('error_log', $logDir . '/error.log');

<?php
// 载入配置
function getConfig() {
    static $config = null;
    if ($config === null) {
        $config = include __DIR__ . '/../config/config.php';
    }
    return $config;
}

// 新增全局站点名称函数
function getSiteName() {
    $config = getConfig();
    return isset($config['site_name']) ? $config['site_name'] : 'LeisurePic';
}

// 简单日志（如需用此版本，保留此函数名即可，避免重复定义）
function logUpload($user, $ip, $filename, $filesize) {
    $logDir = __DIR__ . '/../log';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    // 日志轮转：按天分文件
    $logFile = $logDir . '/upload_log_' . date('Ymd') . '.json';
    // 兼容旧文件名
    $legacyLogFile = $logDir . '/upload_log.json';
    $logs = [];

    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        $logs = json_decode($content, true);
        if (!is_array($logs)) $logs = [];
    } elseif (file_exists($legacyLogFile)) {
        // 首次切换时迁移旧日志
        $content = file_get_contents($legacyLogFile);
        $logs = json_decode($content, true);
        if (!is_array($logs)) $logs = [];
    }

    // 安全过滤
    $user = strip_tags($user);
    $ip = strip_tags($ip);
    $filename = strip_tags($filename);
    // 只允许文件名为字母数字下划线点减号
    $filename = preg_replace('/[^A-Za-z0-9_\.-]/', '', $filename);

    $logs[] = [
        'time' => date('Y-m-d H:i:s'),
        'user' => $user,
        'ip' => $ip,
        'file' => $filename,
        'size' => $filesize,
    ];

    file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function logAction($user, $ip, $action) {
    $logDir = __DIR__ . '/../log';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    // 日志轮转：按天分文件
    $logFile = $logDir . '/action_log_' . date('Ymd') . '.json';
    $legacyLogFile = $logDir . '/action_log.json';
    $logs = [];

    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        $logs = json_decode($content, true);
        if (!is_array($logs)) $logs = [];
    } elseif (file_exists($legacyLogFile)) {
        $content = file_get_contents($legacyLogFile);
        $logs = json_decode($content, true);
        if (!is_array($logs)) $logs = [];
    }

    $logs[] = [
        'time' => date('Y-m-d H:i:s'),
        'user' => $user,
        'ip' => $ip,
        'action' => $action,
    ];

    file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 检查上传权限（上传次数/IP限制）
function checkUploadPermission($ip) {
    $config = getConfig();

    // 黑名单优先
    if (in_array($ip, $config['ip_blacklist'])) {
        return false;
    }
    // 白名单绕过限制
    if (in_array($ip, $config['ip_whitelist'])) {
        return true;
    }

    // 读取日志统计今日上传次数
    $logFile = __DIR__ . '/../log/upload_log.json'; // 修正路径
    $logs = [];
    if (file_exists($logFile)) {
        $logs = json_decode(file_get_contents($logFile), true) ?: [];
    }
    $today = date('Y-m-d');
    $count = 0;
    foreach ($logs as $log) {
        if ($log['ip'] === $ip && strpos($log['time'], $today) === 0) {
            $count++;
        }
    }
    return $count < $config['upload_limit_per_day'];
}

// 简单鉴黄调用示例（第三方接口）
// 这里只是伪代码，您要替换成实际API调用逻辑
function checkAntiPorn($filePath) {
    $config = getConfig();
    if (!$config['enable_antiporn']) return true;

    // 调用第三方API，返回bool
    // 伪代码：
    // $result = some_api_check($filePath, $config['antiporn_api_key']);
    // return $result['safe'] === true;

    // 为方便演示，默认返回true（安全）
    return true;
}

// 列出用户所有上传的文件，示范返回相对路径数组
function listUserFiles($user, $uploadDir) {
    $result = [];
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadDir));
    foreach ($rii as $file) {
        if ($file->isDir()) continue;
        // 这里可以做用户区分，暂时示范列全部
        $relPath = str_replace('\\', '/', substr($file->getPathname(), strlen($uploadDir)));
        $relPath = ltrim($relPath, '/');
        $result[] = $relPath;
    }
    return $result;
}

// 检查是否已安装（未检测到lock文件则跳转到安装页面）
function check_installed_and_redirect() {
    $lockFile = dirname(__DIR__) . '/install.lock';
    if (!file_exists($lockFile)) {
        header('Location: /install/index.php');
        exit;
    }
}

// 图片处理相关函数可以单独写在 app/image_process.php
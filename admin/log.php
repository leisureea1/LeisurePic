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

require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/error_log.php';
require_once __DIR__ . '/../app/core.php';
$csrf_token = csrf_token();
$siteName = function_exists('getSiteName') ? getSiteName() : 'LeisurePic';

$logDir = __DIR__ . '/../log';
$logFile = $logDir . '/upload_log_' . date('Ymd') . '.json';
$legacyLogFile = $logDir . '/upload_log.json';

// 清空日志操作（此时还未输出 HTML）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token($_POST['csrf_token'] ?? '');
    if (isset($_POST['clear_log']) && $_POST['clear_log'] === '1') {
        foreach (glob($logDir . '/upload_log_*.json') as $f) {
            file_put_contents($f, '[]');
        }
        if (file_exists($legacyLogFile)) {
            file_put_contents($legacyLogFile, '[]');
        }
        logAction($_SESSION['user'], $_SERVER['REMOTE_ADDR'], '清空上传日志');
        $_SESSION['msg'] = '日志已清空！';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// 读取日志（确保 header() 已不再调用）
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

// 包含 HTML 输出部分（此时 header() 已执行完毕）
include 'admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-10 col-xl-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white">
                    <span><i class="bi bi-journal-text me-2"></i>上传日志</span>
                    <form method="post" class="mb-0" onsubmit="return confirm('确定要清空所有上传日志吗？');">
                        <input type="hidden" name="clear_log" value="1">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <button type="submit" class="btn btn-danger btn-sm">清空日志</button>
                    </form>
                </div>
                <div class="card-body p-0">
                    <?php
                    if (!empty($_SESSION['msg'])) {
                        echo '<div class="alert alert-success m-3">' . htmlspecialchars($_SESSION['msg']) . '</div>';
                        unset($_SESSION['msg']);
                    }
                    ?>
                    <?php if ($logs): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>时间</th>
                                    <th>用户</th>
                                    <th>IP</th>
                                    <th>文件</th>
                                    <th>大小</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            function formatSizeMB($bytes) {
                                if ($bytes >= 1024 * 1024) return number_format($bytes / 1024 / 1024, 2) . ' MB';
                                if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
                                return $bytes . ' B';
                            }
                            foreach (array_reverse($logs) as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['time']); ?></td>
                                <td><?php echo htmlspecialchars($log['user']); ?></td>
                                <td><?php echo htmlspecialchars($log['ip']); ?></td>
                                <td><?php echo htmlspecialchars($log['file']); ?></td>
                                <td><?php echo formatSizeMB($log['size']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div class="text-muted p-3">暂无日志</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'admin_footer.php'; ?>

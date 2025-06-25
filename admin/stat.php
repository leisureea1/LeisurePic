<?php
require_once __DIR__ . '/../app/error_log.php';
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
require_once __DIR__ . '/admin_header.php';

$uploadDir = __DIR__ . '/../public/uploads';
$cacheFile = __DIR__ . '/../data/stat_cache.json';
$cacheTtl = 300; // 5分钟

$stats = null;
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
    $stats = json_decode(file_get_contents($cacheFile), true);
}
if (!$stats) {
    $totalFiles = 0;
    $totalSize = 0;
    $today = date('Y-m-d');
    $week = date('o-W');
    $month = date('Y-m');
    $todayCount = 0;
    $weekCount = 0;
    $monthCount = 0;
    $typeDist = [];

    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadDir, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $file) {
        if ($file->isDir()) continue;
        $totalFiles++;
        $totalSize += $file->getSize();
        $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
        $typeDist[$ext] = ($typeDist[$ext] ?? 0) + 1;

        $ctime = $file->getCTime();
        $fileDay = date('Y-m-d', $ctime);
        $fileWeek = date('o-W', $ctime);
        $fileMonth = date('Y-m', $ctime);
        if ($fileDay === $today) $todayCount++;
        if ($fileWeek === $week) $weekCount++;
        if ($fileMonth === $month) $monthCount++;
    }
    $stats = [
        'totalFiles' => $totalFiles,
        'totalSize' => $totalSize,
        'todayCount' => $todayCount,
        'weekCount' => $weekCount,
        'monthCount' => $monthCount,
        'typeDist' => $typeDist,
        'cache_time' => time()
    ];
    // 写入缓存
    if (!is_dir(dirname($cacheFile))) mkdir(dirname($cacheFile), 0777, true);
    file_put_contents($cacheFile, json_encode($stats, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
}

// 添加 formatSize 辅助函数
function formatSize($bytes) {
    if ($bytes >= 1024 * 1024) return number_format($bytes / 1024 / 1024, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
?>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow border-0">
                <div class="card-header bg-gradient" style="background:linear-gradient(90deg,#4e73df,#1cc88a);color:#fff;font-size:1.3rem;font-weight:bold;">
                    <i class="bi bi-bar-chart-line me-2"></i>网站统计
                </div>
                <div class="card-body">
                    <div class="row text-center mb-4">
                        <div class="col-6 col-md-3 mb-3">
                            <div class="stat-box bg-primary bg-opacity-10 rounded-3 py-3">
                                <div class="stat-num text-primary" style="font-size:2.1rem;font-weight:bold;"><?php echo $stats['totalFiles']; ?></div>
                                <div class="stat-label text-secondary">图片总数</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <div class="stat-box bg-success bg-opacity-10 rounded-3 py-3">
                                <div class="stat-num text-success" style="font-size:2.1rem;font-weight:bold;"><?php echo $stats['todayCount']; ?></div>
                                <div class="stat-label text-secondary">今日上传</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <div class="stat-box bg-info bg-opacity-10 rounded-3 py-3">
                                <div class="stat-num text-info" style="font-size:2.1rem;font-weight:bold;"><?php echo $stats['weekCount']; ?></div>
                                <div class="stat-label text-secondary">本周上传</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <div class="stat-box bg-warning bg-opacity-10 rounded-3 py-3">
                                <div class="stat-num text-warning" style="font-size:2.1rem;font-weight:bold;"><?php echo $stats['monthCount']; ?></div>
                                <div class="stat-label text-secondary">本月上传</div>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-12 col-md-6 mb-3">
                            <div class="p-3 bg-light rounded-3 shadow-sm h-100">
                                <div class="fw-bold mb-2"><i class="bi bi-hdd-network"></i> 存储占用</div>
                                <div style="font-size:1.2rem;"><?php echo formatSize($stats['totalSize']); ?></div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <div class="p-3 bg-light rounded-3 shadow-sm h-100">
                                <div class="fw-bold mb-2"><i class="bi bi-file-earmark-image"></i> 图片类型分布</div>
                                <?php if ($stats['typeDist']): ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($stats['typeDist'] as $ext => $cnt): ?>
                                            <span class="badge bg-secondary bg-opacity-25 text-dark px-3 py-2"><?php echo strtoupper($ext); ?> <b><?php echo $cnt; ?></b></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">无数据</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="bi bi-info-circle"></i> 统计数据基于上传目录文件实时统计，若有误差请检查目录权限或清理无效文件。
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
.stat-box {
    min-height: 90px;
    box-shadow: 0 2px 12px #eaf2ff;
    border: 1px solid #e3eaf5;
}
</style>
<?php include 'admin_footer.php'; ?>

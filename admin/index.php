<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../app/error_log.php';

session_start();
if (empty($_SESSION['user'])) {
    header('Location: /index.php');
    exit;
}
require_once __DIR__ . '/../app/core.php';

// 初始化统计变量，避免未定义警告
$totalFiles = 0;
$totalSize = 0;
$todayFiles = 0;
$todaySize = 0;
$userCount = 0;
$logCount = 0;

// 统计数据（可根据实际情况调整）
$uploadDir = realpath(__DIR__ . '/../public/uploads/');
$today = date('Y-m-d');

if ($uploadDir && is_dir($uploadDir)) {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadDir));
    foreach ($rii as $file) {
        if ($file->isDir()) continue;
        $totalFiles++;
        $size = $file->getSize();
        $totalSize += $size;
        if (date('Y-m-d', $file->getMTime()) === $today) {
            $todayFiles++;
            $todaySize += $size;
        }
    }
}

// 日志统计
$logFile = __DIR__ . '/../log/upload_log.json';
if (file_exists($logFile)) {
    $logs = json_decode(file_get_contents($logFile), true);
    $logCount = is_array($logs) ? count($logs) : 0;
}

// 用户统计
$users = @include __DIR__ . '/../config/users.php';
if (is_array($users)) {
    $userCount = count($users);
}
?>
<?php include 'admin_header.php'; ?>
<div class="container py-4">
  <div class="row g-4">
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body">
          <div class="fs-2 mb-2 text-primary"><i class="bi bi-images"></i></div>
          <div class="h4 mb-1"><?php echo $totalFiles; ?></div>
          <div class="text-muted">图片总数</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body">
          <div class="fs-2 mb-2 text-success"><i class="bi bi-cloud-upload"></i></div>
          <div class="h4 mb-1"><?php echo number_format($totalSize / 1048576, 2); ?> MB</div>
          <div class="text-muted">存储占用</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body">
          <div class="fs-2 mb-2 text-warning"><i class="bi bi-calendar-day"></i></div>
          <div class="h4 mb-1"><?php echo $todayFiles; ?></div>
          <div class="text-muted">今日上传</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body">
          <div class="fs-2 mb-2 text-info"><i class="bi bi-people"></i></div>
          <div class="h4 mb-1"><?php echo $userCount; ?></div>
          <div class="text-muted">用户数</div>
        </div>
      </div>
    </div>
  </div>
  <div class="row mt-4">
    <div class="col-12 col-md-6">
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-light">
          <b>上传日志总数</b>
        </div>
        <div class="card-body">
          <span class="h5"><?php echo $logCount; ?></span>
          <span class="text-muted ms-2">条</span>
          <a href="log.php" class="btn btn-link btn-sm float-end">查看日志</a>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6">
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-light">
          <b>管理操作</b>
        </div>
        <div class="card-body">
          <a href="image_manage.php" class="btn btn-outline-primary btn-sm me-2 mb-2">图片管理</a>
          <a href="stat.php" class="btn btn-outline-success btn-sm me-2 mb-2">网站统计</a>
          <a href="ip_limit.php" class="btn btn-outline-warning btn-sm me-2 mb-2">IP黑白名单</a>
          <a href="user_manage.php" class="btn btn-outline-info btn-sm me-2 mb-2">用户管理</a>
          <a href="../index.php" class="btn btn-outline-secondary btn-sm me-2 mb-2">返回前台</a>
          <a href="/app/logout.php" class="btn btn-outline-danger btn-sm me-2 mb-2">退出登录</a>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- 可选：引入Bootstrap图标库 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<?php require_once __DIR__ . '/admin_footer.php'; ?>

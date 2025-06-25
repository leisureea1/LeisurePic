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

require_once __DIR__ . '/admin_header.php';
require_once __DIR__ . '/../app/error_log.php';
require_once __DIR__ . '/../app/csrf.php';

$configFile = __DIR__ . '/../config/config.php';
// 强制每次都 require 并禁用 opcode 缓存，确保每次都读取最新配置
if (function_exists('opcache_invalidate')) {
    opcache_invalidate($configFile, true);
}
$config = require $configFile;
require_once __DIR__ . '/../app/core.php';
$siteName = function_exists('getSiteName') ? getSiteName() : (isset($config['site_name']) ? $config['site_name'] : 'LeisurePic');

$errorMsg = '';
// 保存配置
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token($_POST['csrf_token'] ?? '');
    $newConfig = $config;

    foreach ($newConfig as $key => $val) {
        if ($key === 'max_upload_size' && isset($_POST['max_upload_size_mb'])) {
            $newConfig['max_upload_size'] = intval($_POST['max_upload_size_mb']) * 1024 * 1024;
            continue;
        }
        if ($key === 'ads_code') {
            continue;
        }
        if (isset($_POST[$key])) {
            if (is_bool($val)) {
                $newConfig[$key] = ($_POST[$key] == '1' || $_POST[$key] === 'true') ? true : false;
            } elseif (is_array($val)) {
                $lines = trim($_POST[$key]);
                $arr = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $lines)));
                $newConfig[$key] = $arr;
            } else {
                $newConfig[$key] = $_POST[$key];
            }
        }
    }
    // 新增：保存 bark_key 和 bark_server
    $newConfig['bark_key'] = trim($_POST['bark_key'] ?? '');
    $newConfig['bark_server'] = trim($_POST['bark_server'] ?? 'https://api.day.app');

    // 移除鉴黄相关配置项
    unset($newConfig['antiporn_platform'], $newConfig['antiporn_api_key_moderatecontent'], $newConfig['antiporn_api_key_aliyun'], $newConfig['antiporn_api_key_baidu'], $newConfig['antiporn_api_key_tencent'], $newConfig['enable_antiporn']);

    // 安全白名单
    $safeExts = ['jpg','jpeg','png','gif','webp','bmp','txt','pdf','doc','docx','mp4'];

    // 校验 allowed_types
    if (isset($_POST['allowed_types'])) {
        $lines = trim($_POST['allowed_types']);
        $arr = array_filter(array_map('strtolower', array_map('trim', preg_split('/[\r\n,]+/', $lines))));
        $invalid = array_diff($arr, $safeExts);
        if ($invalid) {
            $errorMsg = '不允许的扩展名: ' . implode(', ', $invalid);
        } else {
            $newConfig['allowed_types'] = $arr;
        }
    }

    if ($errorMsg) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($errorMsg) . '</div>';
    } else {
        ob_clean();
        $content = "<?php\nreturn " . var_export($newConfig, true) . ";\n";
        file_put_contents($configFile, $content);
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($configFile, true);
        }
        echo "<script>window.location.href='" . htmlspecialchars($_SERVER['REQUEST_URI']) . "?saved=1';</script>";
        exit;
    }
}
?>

<div class="container py-4">
  <div class="row">
    <div class="col-lg-8 mx-auto">
      <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
          <b><i class="bi bi-gear me-2"></i>全局设置</b>
        </div>
        <div class="card-body">
          <?php if (!empty($_GET['saved'])): ?>
            <div class="alert alert-success" id="saveSuccessMsg">保存成功！</div>
            <script>
              setTimeout(function() {
                var msg = document.getElementById('saveSuccessMsg');
                if (msg) msg.style.display = 'none';
              }, 3000);
            </script>
          <?php endif; ?>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

            <div class="mb-3">
              <label class="form-label">站点名称</label>
              <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($config['site_name']); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">网站域名（如 https://cc.leisureea.com ）域名末尾不要加/</label>
              <input type="text" name="site_domain" class="form-control" value="<?php echo htmlspecialchars($config['site_domain']); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">最大上传大小(MB)</label>
              <input type="number" name="max_upload_size_mb" class="form-control" min="1" step="1" value="<?php echo htmlspecialchars(intval($config['max_upload_size'] / (1024 * 1024))); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">允许上传类型(一行一个或逗号分隔)</label>
              <textarea name="allowed_types" class="form-control" rows="2"><?php echo htmlspecialchars(implode("\n", $config['allowed_types'])); ?></textarea>
            </div>
            <div class="mb-3 row">
              <div class="col">
                <label class="form-label">最小宽度(px)</label>
                <input type="number" name="min_width" class="form-control" value="<?php echo htmlspecialchars($config['min_width']); ?>">
              </div>
              <div class="col">
                <label class="form-label">最小高度(px)</label>
                <input type="number" name="min_height" class="form-control" value="<?php echo htmlspecialchars($config['min_height']); ?>">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">水印图片路径</label>
              <input type="text" name="watermark_image" class="form-control" value="<?php echo htmlspecialchars($config['watermark_image']); ?>">
              <div class="form-text">填写相对路径</div>
            </div>
            <div class="mb-3 row">
              <div class="col">
                <label class="form-label">启用水印</label>
                <select name="enable_watermark" class="form-select">
                  <option value="1" <?php if($config['enable_watermark']) echo 'selected'; ?>>是</option>
                  <option value="0" <?php if(!$config['enable_watermark']) echo 'selected'; ?>>否</option>
                </select>
              </div>
              <div class="col">
                <label class="form-label">启用压缩</label>
                <select name="enable_compress" class="form-select">
                  <option value="1" <?php if($config['enable_compress']) echo 'selected'; ?>>是</option>
                  <option value="0" <?php if(!$config['enable_compress']) echo 'selected'; ?>>否</option>
                </select>
              </div>
              <div class="col">
                <label class="form-label">启用格式转换</label>
                <select name="enable_format_convert" class="form-select">
                  <option value="1" <?php if($config['enable_format_convert']) echo 'selected'; ?>>是</option>
                  <option value="0" <?php if(!$config['enable_format_convert']) echo 'selected'; ?>>否</option>
                </select>
              </div>
            </div>
            <div class="mb-3 row">
              <div class="col">
                <label class="form-label">默认格式</label>
                <input type="text" name="default_format" class="form-control" value="<?php echo htmlspecialchars($config['default_format']); ?>">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">每日上传次数限制</label>
              <input type="number" name="upload_limit_per_day" class="form-control" value="<?php echo htmlspecialchars($config['upload_limit_per_day']); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">最大并发上传数</label>
              <input type="number" name="max_upload_concurrency" class="form-control" min="1" step="1" value="<?php echo isset($config['max_upload_concurrency']) ? htmlspecialchars($config['max_upload_concurrency']) : 3; ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Bark 推送 Key</label>
              <input type="text" name="bark_key" class="form-control" value="<?php echo htmlspecialchars($config['bark_key'] ?? ''); ?>" placeholder="可选，填你的 Bark key">
              <div class="form-text">用于上传成功后推送到 iOS，<a href="https://bark.day.app/" target="_blank">获取 Bark Key</a></div>
            </div>
            <div class="mb-3">
              <label class="form-label">Bark 服务器地址</label>
              <input type="text" name="bark_server" class="form-control" value="<?php echo htmlspecialchars($config['bark_server'] ?? 'https://api.day.app'); ?>" placeholder="https://api.day.app">
              <div class="form-text">如无特殊需求请保持默认</div>
            </div>
            <button type="submit" class="btn btn-primary w-100">保存设置</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
                
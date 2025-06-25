<?php
// 安全提示：安装完成后请务必删除 install 目录，或通过 Web 服务器配置禁止访问 install 目录。
require_once __DIR__ . '/../app/error_log.php';

$lockFile = __DIR__ . '/../install.lock';
$configFile = __DIR__ . '/../config/config.php';
$usersFile = __DIR__ . '/../config/users.php';

if (file_exists($lockFile)) {
    header('Location: /index.php');
    exit;
}

// 环境检测
function check_env() {
    $errors = [];
    if (version_compare(PHP_VERSION, '7.0.0', '<')) $errors[] = 'PHP版本需≥7.0，当前：' . PHP_VERSION;
    $exts = ['fileinfo','iconv','zip','mbstring','openssl','exif'];
    foreach ($exts as $ext) {
        if (!extension_loaded($ext)) $errors[] = "缺少扩展：$ext";
    }
    return $errors;
}

$step = 1;
$env_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step']) && $_POST['step'] == 2) {
        // 第二步：写配置和用户
        $site_domain = trim($_POST['site_domain'] ?? '');
        $site_name = trim($_POST['site_name'] ?? '');
        $admin_user = trim($_POST['admin_user'] ?? '');
        $admin_pass = trim($_POST['admin_pass'] ?? '');

        // 密码强度检查：至少8位且包含字母和数字
        $pass_valid = strlen($admin_pass) >= 8 && preg_match('/[A-Za-z]/', $admin_pass) && preg_match('/\d/', $admin_pass);

        if (!$site_domain || !$site_name || !$admin_user || !$admin_pass) {
            $error = '所有项均不能为空';
            $step = 2;
        } elseif (!$pass_valid) {
            $error = '管理员密码需至少8位且包含字母和数字';
            $step = 2;
        } else {
            // 写config.php
            $config = include $configFile;
            $config['site_domain'] = $site_domain;
            $config['site_name'] = $site_name;
            $configContent = "<?php\nreturn " . var_export($config, true) . ";\n";
            file_put_contents($configFile, $configContent);

            // 写users.php
            $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
            $usersContent = "<?php\nreturn [\n    '" . addslashes($admin_user) . "' => '" . $hash . "',\n];\n";
            file_put_contents($usersFile, $usersContent);

            // 写锁
            file_put_contents($lockFile, "installed at " . date('Y-m-d H:i:s'));

            header('Location: /index.php');
            exit;
        }
    }
    // 若是第一步提交，检测环境
    if (isset($_POST['step']) && $_POST['step'] == 1) {
        $env_errors = check_env();
        if (empty($env_errors)) {
            $step = 2;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>安装<?php
    // 读取站点标题
    $site_name = 'LeisurePic';
    if (file_exists($configFile)) {
        $tmpConfig = include $configFile;
        if (!empty($tmpConfig['site_name'])) $site_name = $tmpConfig['site_name'];
    }
    echo htmlspecialchars($site_name);
  ?>图床</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-7 col-lg-6">
        <div class="alert alert-warning mb-4 text-center" style="font-weight:bold;">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          <span>安全提示：安装完成后请务必删除 <code>install</code> 目录，或通过 Web 服务器配置禁止访问 <code>install</code> 目录！</span>
        </div>
        <div class="card shadow-sm">
          <div class="card-body">
            <h2 class="card-title text-center mb-4">
              安装<?php echo htmlspecialchars($site_name); ?>图床
            </h2>
            <?php if ($step == 1): ?>
              <form method="post">
                <input type="hidden" name="step" value="1">
                <h5 class="mb-3">环境检测</h5>
                <ul class="list-group mb-3">
                  <li class="list-group-item d-flex justify-content-between align-items-center">
                    PHP版本 ≥ 7.0
                    <span class="badge bg-<?php echo version_compare(PHP_VERSION, '7.0.0', '>=') ? 'success' : 'danger'; ?>">
                      <?php echo PHP_VERSION; ?>
                    </span>
                  </li>
                  <?php foreach (['fileinfo','iconv','zip','mbstring','openssl','exif'] as $ext): ?>
                  <li class="list-group-item d-flex justify-content-between align-items-center">
                    <?php echo $ext; ?> 扩展
                    <span class="badge bg-<?php echo extension_loaded($ext) ? 'success' : 'danger'; ?>">
                      <?php echo extension_loaded($ext) ? '已安装' : '缺失'; ?>
                    </span>
                  </li>
                  <?php endforeach; ?>
                </ul>
                <?php
                  $env_errors = check_env();
                  if (!empty($env_errors)) {
                      echo '<div class="alert alert-danger mb-3"><b>环境不合格：</b><br>' . implode('<br>', $env_errors) . '</div>';
                  }
                ?>
                <button type="submit" class="btn btn-primary w-100" <?php echo empty($env_errors) ? '' : 'disabled'; ?>>下一步</button>
              </form>
            <?php elseif ($step == 2): ?>
              <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
              <?php endif; ?>
              <form method="post">
                <input type="hidden" name="step" value="2">
                <div class="mb-3">
                  <label class="form-label">网站域名（如 https://cc.leisureea.com ）</label>
                  <input name="site_domain" class="form-control" required value="<?php echo htmlspecialchars($_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST']); ?>">
                </div>
                <div class="mb-3">
                  <label class="form-label">站点标题</label>
                  <input name="site_name" class="form-control" required value="<?php echo htmlspecialchars($site_name); ?>">
                </div>
                <div class="mb-3">
                  <label class="form-label">管理员账号</label>
                  <input name="admin_user" class="form-control" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">管理员密码</label>
                  <input type="password" name="admin_pass" class="form-control" required pattern="^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$" title="密码需至少8位且包含字母和数字">
                  <small class="text-muted">密码需至少8位且包含字母和数字</small>
                </div>
                <button type="submit" class="btn btn-primary w-100">完成安装</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
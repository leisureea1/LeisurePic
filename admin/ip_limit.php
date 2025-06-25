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
$configFile = __DIR__ . '/../config/config.php';

// 读取配置时强制重新加载，避免opcache/文件缓存导致的延迟
function load_config_fresh($file) {
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($file, true);
    }
    clearstatcache(true, $file);
    return include $file;
}

$config = load_config_fresh($configFile);

$errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token($_POST['csrf_token'] ?? '');
    $black = trim($_POST['ip_blacklist'] ?? '');
    $white = trim($_POST['ip_whitelist'] ?? '');
    $blackArr = array_filter(array_map('trim', explode("\n", $black)));
    $whiteArr = array_filter(array_map('trim', explode("\n", $white)));

    // 检查重复
    $blackSet = array_flip($blackArr);
    $whiteSet = array_flip($whiteArr);
    $conflictIps = [];
    foreach ($blackArr as $ip) {
        if (isset($whiteSet[$ip])) $conflictIps[] = $ip;
    }
    foreach ($whiteArr as $ip) {
        if (isset($blackSet[$ip])) $conflictIps[] = $ip;
    }
    $conflictIps = array_unique($conflictIps);

    if (!empty($conflictIps)) {
        $errorMsg = '以下IP同时存在于黑名单和白名单，请移除重复项：<br>' . htmlspecialchars(implode(', ', $conflictIps));
        // 有冲突时直接返回，不进行保存
    } else {
        // 验证IP和CIDR格式
        function is_valid_ip_or_cidr($ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP)) return true;
            // CIDR格式校验
            if (preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}\/(\d|[12]\d|3[0-2])$/', $ip)) {
                list($base, $mask) = explode('/', $ip);
                return filter_var($base, FILTER_VALIDATE_IP) && $mask >= 0 && $mask <= 32;
            }
            return false;
        }

        $invalidIps = [];
        foreach (array_merge($blackArr, $whiteArr) as $ip) {
            if (!is_valid_ip_or_cidr($ip) || $ip === '0.0.0.0' || $ip === '255.255.255.255') {
                $invalidIps[] = $ip;
            }
        }

        if ($invalidIps) {
            $errorMsg = '以下IP格式非法或不允许: ' . implode(', ', $invalidIps);
        } else {
            $config['ip_blacklist'] = $blackArr;
            $config['ip_whitelist'] = $whiteArr;
            $content = "<?php\nreturn " . var_export($config, true) . ";\n";
            file_put_contents($configFile, $content);
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($configFile, true);
            }
            clearstatcache(true, $configFile);
            header('Location: ip_limit.php?success=1');
            exit;
        }
    }
}
include 'admin_header.php';
?>
<div class="container-fluid">
    <div class="card">
        <div class="card-header">IP黑白名单管理</div>
        <div class="card-body">
            <?php if (!empty($errorMsg)): ?>
                <div class="alert alert-danger"><?php echo $errorMsg; ?></div>
            <?php elseif (isset($_GET['success'])): ?>
                <div class="alert alert-success">保存成功！</div>
                <script>
                  // 只自动刷新一次，并且只在首次加载时执行
                  if (!window._ip_limit_refreshed) {
                    window._ip_limit_refreshed = true;
                    setTimeout(function(){ location.href = 'ip_limit.php'; }, 300);
                  }
                </script>
            <?php endif; ?>
            <div class="alert alert-info mb-3">
              <b>提示：</b>支持单个IP和CIDR段（如 192.168.1.0/24），不允许特殊IP如0.0.0.0、255.255.255.255。所有IP均会严格校验格式，非法输入将被拒绝。
            </div>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <div class="form-group">
                    <label>黑名单（每行一个IP）</label>
                    <textarea name="ip_blacklist" class="form-control" rows="4"><?php
                        // 兼容字符串和数组，保证 implode 第二参数为数组
                        $val = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['ip_blacklist'] ?? '') : ($config['ip_blacklist'] ?? []);
                        if (is_array($val)) {
                            echo htmlspecialchars(implode("\n", $val));
                        } else {
                            echo htmlspecialchars($val);
                        }
                    ?></textarea>
                </div>
                <div class="form-group mt-2">
                    <label>白名单（每行一个IP）</label>
                    <textarea name="ip_whitelist" class="form-control" rows="4"><?php
                        $val = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['ip_whitelist'] ?? '') : ($config['ip_whitelist'] ?? []);
                        if (is_array($val)) {
                            echo htmlspecialchars(implode("\n", $val));
                        } else {
                            echo htmlspecialchars($val);
                        }
                    ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary mt-2">保存</button>
            </form>
        </div>
    </div>
</div>
<?php include 'admin_footer.php'; ?>
    </form>
</div>
</body>
</html>

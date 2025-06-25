<?php
session_start();
$session_timeout = 900;
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
if (function_exists('opcache_invalidate')) {
    opcache_invalidate($configFile, true);
}
$config = require $configFile;
$errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token($_POST['csrf_token'] ?? '');
    $newConfig = $config;
    $newConfig['enable_antiporn'] = isset($_POST['enable_antiporn']) ? ($_POST['enable_antiporn'] == '1' ? true : false) : false;
    $newConfig['sightengine_api_user'] = $_POST['sightengine_api_user'] ?? '';
    $newConfig['sightengine_api_secret'] = $_POST['sightengine_api_secret'] ?? '';
    $newConfig['sightengine_models'] = isset($_POST['sightengine_models']) ? implode(',', $_POST['sightengine_models']) : '';
    $newConfig['sightengine_risk_threshold'] = isset($_POST['sightengine_risk_threshold']) ? floatval($_POST['sightengine_risk_threshold']) : 0.7;
    unset($newConfig['antiporn_platform'], $newConfig['antiporn_api_key_moderatecontent']);
    // 移除 ob_clean()，确保没有任何输出后再重定向
    $content = "<?php\nreturn " . var_export($newConfig, true) . ";\n";
    file_put_contents($configFile, $content);
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($configFile, true);
    }
    echo "<script>window.location.href='" . htmlspecialchars($_SERVER['REQUEST_URI']) . "?saved=1';</script>";
    exit;
}
?>
<div class="container py-4">
  <div class="row">
    <div class="col-lg-8 mx-auto">
      <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
          <b><i class="bi bi-shield-lock me-2"></i>图片鉴黄设置</b>
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
            <div class="mb-3 row">
              <div class="col">
                <label class="form-label">启用鉴黄</label>
                <select name="enable_antiporn" class="form-select">
                  <option value="1" <?php if(isset($config['enable_antiporn']) && $config['enable_antiporn']) echo 'selected'; ?>>是</option>
                  <option value="0" <?php if(!isset($config['enable_antiporn']) || !$config['enable_antiporn']) echo 'selected'; ?>>否</option>
                </select>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Sightengine API User</label>
              <input type="text" name="sightengine_api_user" class="form-control" value="<?php echo htmlspecialchars($config['sightengine_api_user'] ?? ''); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Sightengine API Secret</label>
              <input type="text" name="sightengine_api_secret" class="form-control" value="<?php echo htmlspecialchars($config['sightengine_api_secret'] ?? ''); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">检测内容（可多选）</label>
              <?php
                $modelOptions = [
                  'nudity' => '裸露',
                  'wad' => '武器/酒精/毒品',
                  'offensive' => '冒犯内容',
                  'scam' => '诈骗',
                  'gore' => '血腥',
                  'face-attributes' => '人脸属性'
                ];
                $selectedModels = isset($config['sightengine_models']) ? explode(',', $config['sightengine_models']) : ['nudity','wad'];
              ?>
              <div>
                <?php foreach($modelOptions as $val=>$label): ?>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="sightengine_models[]" value="<?php echo $val; ?>" id="model_<?php echo $val; ?>" <?php if(in_array($val, $selectedModels)) echo 'checked'; ?>>
                    <label class="form-check-label me-3" for="model_<?php echo $val; ?>"><?php echo $label; ?></label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">风险阈值（0-1，默认0.7，越低越严格）</label>
              <input type="number" step="0.01" min="0" max="1" name="sightengine_risk_threshold" class="form-control" value="<?php echo isset($config['sightengine_risk_threshold']) ? htmlspecialchars($config['sightengine_risk_threshold']) : '0.7'; ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">如何申请 Sightengine API KEY？</label>
              <div class="alert alert-info">
                <ol class="mb-0">
                  <li>访问 <a href="https://sightengine.com/" target="_blank">https://sightengine.com/</a> 并注册账号。</li>
                  <li>登录后进入 Dashboard，点击“API Keys”菜单。</li>
                  <li>复制 API User 和 API Secret，粘贴到上方输入框保存即可。</li>
                  <li>免费额度有限，超出需付费。</li>
                </ol>
              </div>
            </div>
            <button type="submit" class="btn btn-primary w-100">保存设置</button>
          </form>
          <hr>
          <?php
            $logFile = __DIR__ . '/../log/anti_porn.json';
            $total = 0;
            $ipList = [];
            $blockIpList = [];
            if (file_exists($logFile)) {
                $logArr = json_decode(file_get_contents($logFile), true) ?: [];
                $total = count($logArr);
                foreach ($logArr as $log) {
                    if (!empty($log['ip'])) {
                        $ipList[] = $log['ip'];
                    }
                    // 拦截成功：response 存在且包含 nudity.raw > 0.7 或 weapon/alcohol/drugs > 0.7
                    if (!empty($log['response']) && is_string($log['response'])) {
                        $res = json_decode($log['response'], true);
                        if (
                            (isset($res['nudity']['raw']) && $res['nudity']['raw'] > 0.7) ||
                            (isset($res['weapon']) && $res['weapon'] > 0.7) ||
                            (isset($res['alcohol']) && $res['alcohol'] > 0.7) ||
                            (isset($res['drugs']) && $res['drugs'] > 0.7)
                        ) {
                            $blockIpList[] = $log['ip'] ?? '';
                        }
                    }
                }
                $ipList = array_unique($ipList);
                $blockIpList = array_unique(array_filter($blockIpList));
            }
          ?>
          <div class="mb-2"><b>已检测图片总数：</b><?php echo $total; ?></div>
          <div class="mb-2"><b>拦截成功数量：</b><?php echo count($blockIpList); ?></div>
          <div class="mb-2"><b>拦截成功的IP：</b><?php echo $blockIpList ? implode('，', $blockIpList) : '无'; ?></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/admin_footer.php'; ?>

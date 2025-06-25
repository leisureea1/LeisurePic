<?php
require_once __DIR__ . '/app/error_log.php';
require_once __DIR__ . '/app/core.php';
require_once __DIR__ . '/app/csrf.php'; 
check_installed_and_redirect();
session_start();
$config = getConfig();
$siteName = getSiteName();
$loggedIn = isset($_SESSION['user']);
$username = $loggedIn ? $_SESSION['user'] : '';
?>
<!DOCTYPE html>
<html lang="zh">
<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($siteName); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- 替换为 Bootstrap 官方CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="/public/static/main.css" rel="stylesheet">
</head>
<body>
  <div class="main-card">
    <div class="card-header">
      <h2>
        <?php echo htmlspecialchars($siteName); ?>
      </h2>
      <div class="card-actions">
        <?php if ($loggedIn): ?>
        <div class="user-dropdown" id="userDropdown">
          <span
            class="d-inline-flex align-items-center rounded-pill border border-primary bg-white px-2 py-1 shadow-sm"
            style="cursor:pointer; transition:box-shadow .2s; min-width:0; max-width: 140px; overflow: hidden;"
            onclick="toggleUserMenu()"
            onmouseover="this.style.boxShadow='0 0 0 0.2rem #4e73df33'"
            onmouseout="this.style.boxShadow='0 1px 8px #eee'"
          >
            <img src="https://api.dicebear.com/7.x/identicon/svg?seed=<?php echo urlencode($username); ?>"
                 class="user-avatar me-2 border border-2 border-primary flex-shrink-0"
                 alt="头像"
                 style="background:#fff;box-shadow:0 1px 8px #eee;min-width:36px;min-height:36px;">
            <?php if ($username !== ''): ?>
              <span class="d-inline-block align-middle text-truncate" style="font-weight:bold;max-width:80px;color:#222;vertical-align:middle;">
                <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>
              </span>
            <?php else: ?>
              <span class="d-inline-block align-middle" style="color:#bbb;">未登录</span>
            <?php endif; ?>
            <i class="icon icon-caret-down ms-1"></i>
          </span>
          <div class="user-menu" id="userMenu">
            <a href="admin.php"><i class="icon icon-dashboard"></i> 进入后台</a>
            <a href="app/logout.php" id="logoutBtn"><i class="icon icon-signout"></i> 退出登录</a>
          </div>
        </div>
        <?php else: ?>
        <button type="button" class="btn login-btn" id="showLoginBtn">登录</button>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-body" style="padding-top:1.2rem; padding-bottom:1.2rem; display:flex; flex-direction:column; align-items:center; justify-content:flex-start;">
      <div id="pasteDropArea" class="mb-2 d-flex align-items-center justify-content-center border border-2 border-primary rounded-3 bg-light"
           style="min-height:120px; height:120px; width:100%; cursor:pointer; transition:box-shadow .2s;"
           tabindex="0">
        <span class="text-muted" style="font-size:1.08em;">点击、粘贴图片或拖拽图片到此区域上传</span>
      </div>
      <form id="uploadForm" class="mb-2" style="width:100%;" enctype="multipart/form-data" method="post" action="app/upload.php">
        <div class="form-group mb-2">
          <input type="file" name="file[]" class="form-control" id="fileInput" required accept="image/*" multiple>
        </div>
        <button type="button" class="btn btn-danger btn-block w-100 py-2 mb-2" id="clearFilesBtn" style="font-weight:bold;">清除图片</button>
        <button type="button" class="btn upload-btn btn-block w-100 py-2" id="startUploadBtn">开始上传</button>
      </form>
      <!-- 广告位：上传按钮下方 -->
      <div id="adsArea" class="mb-3" style="width:100%;text-align:center;">
        <?php if (!empty($config['ads_code'])): ?>
          <?php echo $config['ads_code']; ?>
        <?php endif; ?>
      </div>
      <div id="uploadResult" class="mt-3" style="width:100%;"></div>
      <div id="uploadProgress" class="progress mt-2" style="height: 18px; display: none; width: 100%;">
        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar"
             style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" id="progressBar">
          0%
        </div>
      </div>
      <div id="progressInfo" class="text-center text-muted small mt-1" style="display:none;"></div>
    </div>
  </div>

  <!-- 登录模态框 -->
  <div class="modal fade" id="loginModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content" style="border-radius:12px;">
        <div class="modal-header">
          <h4 class="modal-title" id="loginModalLabel">用户登录</h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
        </div>
        <div class="modal-body">
          <form id="loginForm" autocomplete="off">
            <div class="form-group mb-3">
              <input name="username" class="form-control" placeholder="用户名" autocomplete="username">
            </div>
            <div class="form-group mb-3">
              <input name="password" type="password" class="form-control" placeholder="密码" autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-success btn-block w-100 py-2">登录</button>
            <div id="loginMsg" class="mt-2 text-danger"></div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div id="copiedToast"></div>
  <!-- 替换为 Bootstrap 官方CDN -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  window.csrf_token = "<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>";
</script>
  <script>window.config = <?php echo json_encode($config); ?>;</script>
  <script src="/public/static/main.js"></script>
</body>
</html>
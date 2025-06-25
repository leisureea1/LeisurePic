<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../app/core.php';
require_once __DIR__ . '/../app/error_log.php'; // 引入错误日志设置
check_installed_and_redirect();

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

$sidebarMenus = [
    ['file' => 'index.php',      'title' => '后台首页'],
    ['file' => 'image_manage.php',     'title' => '图片管理'],
    ['file' => 'antiporn_settings.php', 'title' => '图片鉴黄'],
    ['file' => 'stat.php',      'title' => '网站统计'],
    ['file' => 'ip_limit.php',     'title' => 'IP黑白名单'],
    ['file' => 'log.php', 'title' => '上传日志'],
    ['file' => 'user_manage.php',      'title' => '用户管理'],
    ['file' => 'settings.php',   'title' => '全局设置'],
    ['file' => '../index.php',   'title' => '返回前台'],
    ['file' => 'update.php',   'title' => '更新管理'],  
     
];
?>
<!DOCTYPE html>
<html lang="zh">
<head>
  <meta charset="UTF-8">
  <title>后台管理 - LeisurePic</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8fafc; }
    .admin-layout {
      display: flex;
      min-height: 100vh;
      flex-direction: row;
    }
    .admin-sidebar {
      width: 210px;
      background: linear-gradient(180deg,#4e73df 70%,#1cc88a 100%);
      color: #fff;
      padding: 2rem 1rem 1rem 1rem;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      min-height: 100vh;
      position: relative;
      z-index: 1051;
      border-radius: 0 1.5rem 1.5rem 0;
      box-shadow: 2px 0 16px rgba(80,120,200,0.10);
      transition: width 0.2s, left 0.2s, box-shadow 0.2s, border-radius 0.2s;
      overflow-x: visible;
    }
    .admin-sidebar .sidebar-title {
      font-size: 1.35rem;
      font-weight: bold;
      margin-bottom: 2.2rem;
      letter-spacing: 2px;
      width: 100%;
      text-align: left;
      transition: opacity 0.2s;
      color: #fff;
      text-shadow: 0 2px 8px #3a7bd5cc;
    }
    .admin-sidebar .sidebar-menu {
      width: 100%;
      display: flex;
      flex-direction: column;
      gap: 1.2rem;
    }
    .admin-sidebar .sidebar-menu a {
      color: #fff;
      text-decoration: none;
      font-size: 1.08rem;
      padding: 0.5em 1em;
      border-radius: 10px;
      transition: background .18s, color .18s, box-shadow .18s;
      display: flex;
      align-items: center;
      gap: 0.5em;
      font-weight: 500;
      box-shadow: none;
      position: relative;
    }
    .admin-sidebar .sidebar-menu a.active,
    .admin-sidebar .sidebar-menu a:hover {
      background: rgba(255,255,255,0.22);
      color: #fff;
      box-shadow: 0 2px 8px #eaf2ff;
      text-decoration: none;
    }
    .admin-sidebar .sidebar-menu a:before {
      content: '';
      display: block;
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #fff;
      opacity: 0;
      margin-right: 0.5em;
      transition: opacity .18s;
    }
    .admin-sidebar .sidebar-menu a.active:before {
      opacity: 1;
    }
    .admin-sidebar .sidebar-user {
      margin-top: auto;
      font-size: 0.98rem;
      color: #e0f7fa;
      width: 100%;
      text-align: left;
      border-top: 1px solid #fff3;
      padding-top: 1.2rem;
      margin-top: 2rem;
    }
    .admin-sidebar .logout-btn {
      color: #fff;
      border: 1.5px solid #fff;
      background: transparent;
      border-radius: 20px;
      padding: 0.2em 1.2em;
      margin-top: 0.8em;
      transition: background .2s, color .2s;
      text-decoration: none;
      display: inline-block;
      font-size: 1em;
    }
    .admin-sidebar .logout-btn:hover {
      background: #fff;
      color: #4e73df;
      text-decoration: none;
    }
    .admin-main {
      flex: 1 1 auto;
      padding: 2.5rem 2rem 2rem 2rem;
      min-width: 0;
      background: #fff;
      border-radius: 0 0 1.5rem 1.5rem;
      margin: 2rem 2rem 2rem 0;
      box-shadow: 0 2px 16px rgba(0,0,0,0.06);
      transition: margin-left 0.2s;
    }
    /* 侧边栏收起样式 */
    .admin-sidebar.collapsed {
      width: 56px;
      padding-left: 0.5rem;
      padding-right: 0.5rem;
      border-radius: 0 1.5rem 1.5rem 0;
    }
    .admin-sidebar.collapsed .sidebar-title,
    .admin-sidebar.collapsed .sidebar-user,
    .admin-sidebar.collapsed .sidebar-menu a span {
      opacity: 0;
      width: 0;
      overflow: hidden;
      display: inline-block;
      transition: opacity 0.2s, width 0.2s;
    }
    .admin-sidebar.collapsed .sidebar-menu a {
      text-align: center;
      padding-left: 0.2em;
      padding-right: 0.2em;
      justify-content: center;
    }
    /* 美化收起按钮 */
    .admin-sidebar .sidebar-toggle {
      position: absolute;
      top: 1.2rem;
      right: -22px;
      width: 44px;
      height: 44px;
      background: #fff;
      color: #4e73df;
      border-radius: 50%;
      border: 2.5px solid #4e73df;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      z-index: 1100;
      box-shadow: 0 4px 16px #b6d0fa;
      transition: background .2s, color .2s, left 0.2s, box-shadow .2s;
      outline: none;
      border-width: 2.5px;
    }
    .admin-sidebar .sidebar-toggle:active {
      background: #eaf2ff;
      color: #1cc88a;
      box-shadow: 0 2px 8px #b6d0fa;
    }
    .admin-sidebar .sidebar-toggle .hamburger {
      width: 22px;
      height: 22px;
      display: inline-block;
      position: relative;
    }
    .admin-sidebar .sidebar-toggle .hamburger span {
      display: block;
      height: 3px;
      width: 100%;
      background: #4e73df;
      border-radius: 2px;
      margin: 4px 0;
      transition: all 0.25s cubic-bezier(.4,2,.6,1);
    }
    .admin-sidebar.collapsed .sidebar-toggle .hamburger span:nth-child(1) {
      transform: translateY(7px) rotate(45deg);
    }
    .admin-sidebar.collapsed .sidebar-toggle .hamburger span:nth-child(2) {
      opacity: 0;
    }
    .admin-sidebar.collapsed .sidebar-toggle .hamburger span:nth-child(3) {
      transform: translateY(-7px) rotate(-45deg);
    }
    /* 手机端适配：侧边栏浮动，内容全宽 */
    @media (max-width: 991px) {
      .admin-layout {
        flex-direction: row;
      }
      .admin-sidebar {
        position: fixed;
        left: -220px;
        top: 0;
        bottom: 0;
        height: 100vh;
        z-index: 1051;
        border-radius: 0 1.5rem 1.5rem 0;
        box-shadow: 2px 0 16px rgba(80,120,200,0.10);
        transition: left 0.2s, width 0.2s, box-shadow 0.2s;
        background: linear-gradient(180deg,#4e73df 80%,#1cc88a 100%);
      }
      .admin-sidebar.open {
        left: 0;
        animation: sidebarFadeIn .18s;
      }
      @keyframes sidebarFadeIn {
        from { left: -220px; opacity: 0.5; }
        to { left: 0; opacity: 1; }
      }
      .admin-main {
        margin: 1rem 0.5rem 0.5rem 0.5rem;
        padding: 1.2rem 0.7rem;
        border-radius: 1.5rem;
        margin-left: 0 !important;
      }
      .admin-sidebar.collapsed {
        width: 56px;
      }
      .admin-sidebar .sidebar-toggle {
        position: fixed;
        left: 14px;
        top: 14px;
        right: auto;
        z-index: 1102;
        box-shadow: 0 4px 16px #b6d0fa;
      }
      .admin-sidebar.open .sidebar-toggle {
        left: 224px;
      }
      .sidebar-mask.active {
        display: block;
      }
    }
    .sidebar-mask {
      display: none;
      position: fixed;
      z-index: 1050;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0,0,0,0.18);
    }
    .sidebar-mask.active {
      display: block;
    }
  </style>
</head>
<body>
<div class="admin-layout">
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-title"><span>LeisurePic 后台</span></div>
    <div class="sidebar-toggle" id="sidebarToggle" title="展开/收起侧边栏" tabindex="0">
      <span class="hamburger">
        <span></span>
        <span></span>
        <span></span>
      </span>
    </div>
    <nav class="sidebar-menu">
      <?php foreach ($sidebarMenus as $item): ?>
        <a href="/admin/<?php echo $item['file']; ?>" class="<?php echo basename($_SERVER['PHP_SELF']) === basename($item['file']) ? 'active' : ''; ?>">
          <span><?php echo htmlspecialchars($item['title']); ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-user mt-4">
      <span>欢迎，<?php echo htmlspecialchars($_SESSION['user']); ?><br>
      <a href="/app/logout.php" class="logout-btn">退出</a></span>
    </div>
  </aside>
  <div class="sidebar-mask" id="sidebarMask"></div>
  <main class="admin-main">
  <script>
    // 侧边栏收起/展开逻辑
    (function() {
      var sidebar = document.getElementById('adminSidebar');
      var toggle = document.getElementById('sidebarToggle');
      var mask = document.getElementById('sidebarMask');
      // PC端收起/展开
      toggle.onclick = function(e) {
        e.stopPropagation();
        if (window.innerWidth > 991) {
          sidebar.classList.toggle('collapsed');
        } else {
          sidebar.classList.toggle('open');
          mask.classList.toggle('active', sidebar.classList.contains('open'));
        }
      };
      // 手机端点击遮罩关闭
      mask.onclick = function() {
        sidebar.classList.remove('open');
        mask.classList.remove('active');
      };
      // 手机端点击内容区关闭侧边栏
      document.body.addEventListener('click', function(e) {
        if (window.innerWidth <= 991 && sidebar.classList.contains('open')) {
          if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
            sidebar.classList.remove('open');
            mask.classList.remove('active');
          }
        }
      });
      // 键盘支持：回车/空格收起展开
      toggle.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
          toggle.click();
        }
      });
    })();
  </script>

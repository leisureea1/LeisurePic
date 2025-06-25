<?php
session_start();
require_once __DIR__ . '/../app/csrf.php';
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
require_once __DIR__ . '/../app/core.php';
$config = include __DIR__ . '/../config/config.php';
$siteName = function_exists('getSiteName') ? getSiteName() : (isset($config['site_name']) ? $config['site_name'] : 'LeisurePic');

// 只扫描 public/uploads 目录
$uploadDir = realpath(__DIR__ . '/../public/uploads/');
function getAllImages($dir) {
    $result = [];
    if (!$dir || !is_dir($dir)) return $result;
    try {
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($rii as $file) {
            if ($file->isDir()) continue;
            $relPath = str_replace('\\', '/', substr($file->getPathname(), strlen($dir)));
            $relPath = ltrim($relPath, '/');
            $result[] = $relPath;
        }
    } catch (Exception $e) {
        return [];
    }
    return $result;
}
$images = getAllImages($uploadDir);

// 获取图片上传时间映射
function getImageUploadTimes($logFile) {
    $map = [];
    if (file_exists($logFile)) {
        $logs = json_decode(file_get_contents($logFile), true) ?: [];
        foreach ($logs as $log) {
            if (!empty($log['file']) && !empty($log['time'])) {
                $map[$log['file']] = $log['time'];
            }
        }
    }
    return $map;
}

$logFile = __DIR__ . '/../log/upload_log.json';
$imageTimes = getImageUploadTimes($logFile);

// 使用 filemtime() 对图片进行排序，最新在上最老在下
usort($images, function($a, $b) use ($uploadDir) {
    $aPath = $uploadDir . '/' . $a;
    $bPath = $uploadDir . '/' . $b;
    $aTime = file_exists($aPath) ? filemtime($aPath) : 0;
    $bTime = file_exists($bPath) ? filemtime($bPath) : 0;
    // 如果都不存在，保持原顺序
    if ($aTime === $bTime) return 0;
    // 按时间降序
    return $aTime < $bTime ? 1 : -1;
});

// 获取站点域名
$siteDomain = !empty($config['site_domain']) ? rtrim($config['site_domain'], '/') : '';
include 'admin_header.php';

// 分页参数
$pageSize = 5;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$totalImages = count($images);
$totalPages = ceil($totalImages / $pageSize);
$offset = ($page - 1) * $pageSize;
$pagedImages = array_slice($images, $offset, $pageSize);
?>
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10 col-xl-8">
            <div class="card shadow border-0">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center" style="border-radius: 0.7rem 0.7rem 0 0;">
                    <span>
                        <i class="bi bi-images me-2"></i>
                        <?php echo htmlspecialchars($siteName); ?> - 图片管理
                    </span>
                    <?php if ($images): ?>
                    <form id="batchDeleteForm" method="post" action="image_delete.php" class="d-inline mb-0" onsubmit="return confirm('确定批量删除选中图片？');">
                        <input type="hidden" name="batch" value="1">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <button type="submit" class="btn btn-danger btn-sm" id="batchDeleteBtn" disabled>
                            <i class="bi bi-trash"></i> 批量删除
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if ($images): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:32px;"><input type="checkbox" id="selectAll"></th>
                                    <th>预览</th>
                                    <th>文件名</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($pagedImages as $img): 
                                $url = '/public/uploads/' . $img;
                                $fullUrl = $siteDomain . $url;
                                $imgPath = $uploadDir . '/' . $img;
                                $imgSize = (file_exists($imgPath) && is_file($imgPath)) ? filesize($imgPath) : 0;
                                $imgSizeKB = $imgSize > 0 ? round($imgSize / 1024, 2) : 0;
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="img-checkbox form-check-input" name="filenames[]" value="<?php echo htmlspecialchars($img); ?>" form="batchDeleteForm">
                                </td>
                                <td>
                                    <img src="<?php echo htmlspecialchars($fullUrl); ?>" data-src="<?php echo htmlspecialchars($fullUrl); ?>" style="max-width:100px;max-height:70px;object-fit:cover;cursor:pointer;" class="rounded border preview-img lazy-img" alt="" data-full="<?php echo htmlspecialchars($fullUrl); ?>">
                                </td>
                                <td class="text-break" style="max-width:320px;">
                                    <span class="badge bg-light text-dark" style="font-size:0.95em;"><?php echo htmlspecialchars($img); ?></span>
                                    <span class="badge bg-secondary ms-2" style="font-size:0.92em;"><?php echo $imgSizeKB; ?> KB</span>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <form method="post" action="image_delete.php" class="d-inline" onsubmit="return confirm('确定删除？');" style="margin:0; padding:0;">
                                            <input type="hidden" name="filename" value="<?php echo htmlspecialchars($img); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                            <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i> 删除</button>
                                        </form>
                                        <button type="button" class="btn btn-outline-primary btn-sm show-link-btn" 
                                            data-url="<?php echo htmlspecialchars($fullUrl); ?>"
                                            data-bs-toggle="modal" data-bs-target="#linkModal"
                                            title="查看链接">
                                            <i class="bi bi-link-45deg"></i> 链接
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- 分页导航 -->
                    <nav>
                      <ul class="pagination justify-content-center my-3">
                        <?php
                        $start = max(1, $page - 1);
                        $end = min($totalPages, $page + 1);
                        if ($end - $start < 2) {
                            if ($start == 1) $end = min($totalPages, $start + 2);
                            if ($end == $totalPages) $start = max(1, $end - 2);
                        }
                        ?>
                        <?php if ($page > 1): ?>
                          <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page-1; ?>">上一页</a>
                          </li>
                        <?php endif; ?>
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                          <li class="page-item<?php if ($i == $page) echo ' active'; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                          </li>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                          <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page+1; ?>">下一页</a>
                          </li>
                        <?php endif; ?>
                      </ul>
                    </nav>
                    <script>
                    // 多选全选
                    document.getElementById('selectAll')?.addEventListener('change', function() {
                        let checked = this.checked;
                        document.querySelectorAll('.img-checkbox').forEach(cb => { cb.checked = checked; });
                        updateBatchDeleteBtn();
                    });
                    document.querySelectorAll('.img-checkbox').forEach(cb => {
                        cb.addEventListener('change', updateBatchDeleteBtn);
                    });
                    function updateBatchDeleteBtn() {
                        const checkedCount = document.querySelectorAll('.img-checkbox:checked').length;
                        const btn = document.getElementById('batchDeleteBtn');
                        if (btn) btn.disabled = checkedCount === 0;
                    }
                    // 链接弹窗逻辑
                    document.querySelectorAll('.show-link-btn').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const url = this.getAttribute('data-url');
                            const md = `![<?php echo htmlspecialchars($siteName); ?>](${url})`;
                            const html = `<img src="${url}" alt="<?php echo htmlspecialchars($siteName); ?>">`;
                            document.getElementById('linkModalBody').innerHTML = `
                              <div class="link-group mb-3">
                                <span class="link-label">直链</span>
                                <input type="text" class="form-control d-inline-block link-input" style="width:60%;" value="${url}" readonly id="linkDirect">
                                <button class="btn btn-outline-secondary copy-btn" type="button" onclick="copyLink('linkDirect')"><i class="bi bi-clipboard"></i> 复制</button>
                              </div>
                              <div class="link-group mb-3">
                                <span class="link-label">Md</span>
                                <input type="text" class="form-control d-inline-block link-input" style="width:60%;" value="${md}" readonly id="linkMd">
                                <button class="btn btn-outline-secondary copy-btn" type="button" onclick="copyLink('linkMd')"><i class="bi bi-clipboard"></i> 复制</button>
                              </div>
                              <div class="link-group mb-2">
                                <span class="link-label">H5</span>
                                <input type="text" class="form-control d-inline-block link-input" style="width:60%;" value='${html}' readonly id="linkHtml">
                                <button class="btn btn-outline-secondary copy-btn" type="button" onclick="copyLink('linkHtml')"><i class="bi bi-clipboard"></i> 复制</button>
                              </div>
                            `;
                        });
                    });
                    // 图片点击弹窗大图
                    document.querySelectorAll('.preview-img').forEach(img => {
                        img.addEventListener('click', function() {
                            var modal = new bootstrap.Modal(document.getElementById('previewImgModal'));
                            document.getElementById('previewImgModalImg').src = this.getAttribute('data-full');
                            modal.show();
                        });
                    });
                    // 懒加载
                    function lazyLoadImages() {
                        const lazyImgs = document.querySelectorAll('img.lazy-img:not([data-loaded])');
                        if ('IntersectionObserver' in window) {
                            let observer = new IntersectionObserver((entries, obs) => {
                                entries.forEach(entry => {
                                    if (entry.isIntersecting) {
                                        let img = entry.target;
                                        img.src = img.getAttribute('data-src');
                                        img.setAttribute('data-loaded', '1');
                                        obs.unobserve(img);
                                    }
                                });
                            });
                            lazyImgs.forEach(img => observer.observe(img));
                        } else {
                            lazyImgs.forEach(img => {
                                img.src = img.getAttribute('data-src');
                                img.setAttribute('data-loaded', '1');
                            });
                        }
                    }
                    lazyLoadImages();
                    // 复制功能
                    window.copyLink = function(inputId) {
                        const input = document.getElementById(inputId);
                        if (input) {
                            input.select();
                            input.setSelectionRange(0, 99999);
                            document.execCommand('copy');
                            showCopyToast();
                        }
                    };
                    function showCopyToast() {
                        let toast = document.getElementById('adminCopyToast');
                        if (!toast) {
                            toast = document.createElement('div');
                            toast.id = 'adminCopyToast';
                            toast.style.position = 'fixed';
                            toast.style.top = '18px';
                            toast.style.left = '50%';
                            toast.style.transform = 'translateX(-50%)';
                            toast.style.background = 'rgba(60,60,60,0.95)';
                            toast.style.color = '#fff';
                            toast.style.padding = '8px 24px';
                            toast.style.borderRadius = '24px';
                            toast.style.fontSize = '1rem';
                            toast.style.zIndex = 99999;
                            toast.style.boxShadow = '0 2px 12px rgba(0,0,0,0.12)';
                            toast.style.display = 'none';
                            document.body.appendChild(toast);
                        }
                        toast.innerText = '已复制';
                        toast.style.display = 'block';
                        clearTimeout(toast._timer);
                        toast._timer = setTimeout(() => { toast.style.display = 'none'; }, 1200);
                    }
                    </script>
                    <?php else: ?>
                        <div class="text-muted p-3">暂无图片或上传目录不存在</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- 链接弹窗 -->
<div class="modal fade" id="linkModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:14px;">
      <div class="modal-header">
        <h5 class="modal-title">图片链接</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
      </div>
      <div class="modal-body" id="linkModalBody">
        <!-- 动态填充 -->
      </div>
    </div>
  </div>
</div>
<!-- 大图预览弹窗 -->
<div class="modal fade" id="previewImgModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="background:rgba(0,0,0,0.85);border-radius:14px;">
      <div class="modal-body text-center p-0" style="position:relative;">
        <img id="previewImgModalImg" src="" style="max-width:96vw;max-height:80vh;border-radius:12px;box-shadow:0 2px 16px #222;" alt="大图预览">
        <button type="button" class="btn-close btn-close-white position-absolute" style="top:18px;right:24px;z-index:10;" data-bs-dismiss="modal" aria-label="关闭"></button>
      </div>
    </div>
  </div>
</div>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
.link-group { margin-bottom: 1.2rem; }
.link-label { font-weight: bold; color: #4e73df; width: 80px; }
.link-input { font-size: 1em; border-radius: 8px; margin-right: 8px; }
.copy-btn { border-radius: 8px; font-size: 0.98em; }
</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'admin_footer.php'; ?>
</body>
</html>

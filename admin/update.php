<?php
require_once __DIR__ . '/admin_header.php';
?>
<div class="container py-4">
  <div class="row">
    <div class="col-lg-8 mx-auto">
      <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
          <b><i class="bi bi-arrow-repeat me-2"></i>系统更新</b>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <button type="button" class="btn btn-info" id="checkUpdateBtn">检测新版本</button>
            <span id="updateResult" class="ms-3"></span>
          </div>
          <!-- 更新弹窗 -->
          <div class="modal fade" id="updateModal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title" id="updateModalLabel">系统更新</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body" id="updateModalBody">
                  <div class="d-flex align-items-center">
                    <div class="spinner-border text-info me-3" role="status"></div>
                    <span>系统正在更新中，请勿关闭页面...</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <script>
          document.getElementById('checkUpdateBtn').onclick = function() {
            var btn = this;
            var result = document.getElementById('updateResult');
            btn.disabled = true;
            result.innerHTML = '检测中...';
            fetch('../app/check_update.php', {cache:'no-store'})
              .then(r => r.json())
              .then(function(res) {
                if (res.error) {
                  result.innerHTML = '<span class="text-danger">' + res.error + '</span>';
                  return;
                }
                let html = '';
                html += '<div><b>当前版本：</b>' + (res.local_version || '-') + (res.local_time ? '（' + res.local_time + '）' : '') + '</div>';
                html += '<div><b>最新版本：</b>' + (res.remote_version || '-') + (res.remote_time ? '（' + res.remote_time + '）' : '') + '</div>';
                if (res.desc) {
                  html += '<div class="mb-2"><b>更新内容：</b><span class="text-muted">' + res.desc + '</span></div>';
                }
                if (res.has_update) {
                  html += '<div class="mt-2">';
                  html += '<button type="button" class="btn btn-success me-2" id="doUpdateBtn">立即更新</button>';
                  html += '<button type="button" class="btn btn-secondary" id="cancelUpdateBtn">取消</button>';
                  html += '</div>';
                } else {
                  html += '<span class="text-secondary">当前已是最新版本</span>';
                }
                result.innerHTML = html;

                // 绑定按钮事件
                if (res.has_update) {
                  document.getElementById('doUpdateBtn').onclick = function() {
                    var modal = new bootstrap.Modal(document.getElementById('updateModal'));
                    document.getElementById('updateModalBody').innerHTML =
                      '<div class="d-flex align-items-center">' +
                      '<div class="spinner-border text-info me-3" role="status"></div>' +
                      '<span>系统正在更新中，请勿关闭页面...</span>' +
                      '</div>';
                    modal.show();
                    fetch('../app/do_update.php')
                      .then(r => r.text())
                      .then(function(txt) {
                        document.getElementById('updateModalBody').innerHTML =
                          '<div class="text-center py-3">' +
                          (txt.indexOf('成功') !== -1
                            ? '<span class="text-success"><i class="bi bi-check-circle-fill me-2"></i>' + txt + '</span>'
                            : '<span class="text-danger"><i class="bi bi-x-circle-fill me-2"></i>' + txt + '</span>') +
                          '<br><button class="btn btn-primary mt-3" onclick="location.reload()">刷新页面</button>' +
                          '</div>';
                        if (txt.indexOf('成功') !== -1) setTimeout(function(){ location.reload(); }, 2000);
                      })
                      .catch(function() {
                        document.getElementById('updateModalBody').innerHTML =
                          '<div class="text-danger text-center py-3">更新失败，请检查服务器权限<br><button class="btn btn-secondary mt-3" data-bs-dismiss="modal">关闭</button></div>';
                      });
                  };
                  document.getElementById('cancelUpdateBtn').onclick = function() {
                    result.innerHTML = '';
                  };
                }
              })
              .catch(function() {
                result.innerHTML = '<span class="text-danger">检测失败，请稍后重试</span>';
              })
              .finally(function() {
                btn.disabled = false;
              });
          };
          </script>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/admin_footer.php'; ?>

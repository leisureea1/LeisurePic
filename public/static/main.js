// 上传入口
const fileInput = document.getElementById('fileInput');
const pasteDropArea = document.getElementById('pasteDropArea');
const uploadResult = document.getElementById('uploadResult');
const uploadProgress = document.getElementById('uploadProgress');
const progressBar = document.getElementById('progressBar');
const progressInfo = document.getElementById('progressInfo');
const clearFilesBtn = document.getElementById('clearFilesBtn');
const uploadForm = document.getElementById('uploadForm');
const startUploadBtn = document.getElementById('startUploadBtn');
let selectedFiles = null;
let canUpload = true;
let maxConcurrency = 3; // 默认并发数
let isBlacklisted = false; // 全局变量，便于后续判断

// 检查上传权限
function refreshUploadPermission() {
  fetch('app/check_upload.php', {credentials: 'same-origin'})
    .then(r => r.json())
    .then(res => {
      canUpload = !!(res && (res.allowed === true || res.allowed === 'true'));
      isBlacklisted = !!(res && res.is_blacklisted);

      if (!canUpload || isBlacklisted) {
        if (startUploadBtn) {
          startUploadBtn.disabled = true;
          startUploadBtn.classList.add('disabled');
        }
        let msg = isBlacklisted
          ? '您的IP已被禁止上传'
          : '您已到达今日上传限制';
        uploadResult.innerHTML = `<div class="alert alert-danger mb-2" style="font-weight:bold;">${msg}</div>`;
      } else {
        if (startUploadBtn) {
          startUploadBtn.disabled = false;
          startUploadBtn.classList.remove('disabled');
        }
      }
    });
}

// 获取并发数配置（从config.php输出的window.config读取）
function fetchMaxConcurrency() {
  if (window.config && window.config.max_upload_concurrency) {
    maxConcurrency = parseInt(window.config.max_upload_concurrency) || 3;
  }
}
fetchMaxConcurrency();

// 并发上传函数（fetch实现，支持maxConcurrency），
function uploadFiles(files) {
  if (!files || !files.length) return;
  uploadResult.innerHTML = '<div class="alert alert-info mb-2" style="font-weight:bold;"><span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>图片上传中，请稍候…</div>';
  let results = new Array(files.length);
  let finished = 0;
  let uploading = 0;
  let queue = [];
  for (let i = 0; i < files.length; i++) {
    queue.push({ idx: i, file: files[i] });
  }

  function next() {
    while (uploading < maxConcurrency && queue.length > 0) {
      const { idx, file } = queue.shift();
      uploading++;
      uploadOne(idx, file);
    }
  }

  function uploadOne(idx, file) {
    let formData = new FormData();
    formData.append('file', file);
    // 加入csrf_token
    if (window.csrf_token) {
      formData.append('csrf_token', window.csrf_token);
    } else if (document.querySelector('input[name="csrf_token"]')) {
      formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    }
    fetch('app/upload.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        results[idx] = '<div class="mb-2">' + showImageInfoHtml(res.url) + '</div>';
      } else {
        // 检查是否为图片过大错误
        let errMsg = (res.error || res.message || '上传失败');
        if (errMsg.indexOf('文件过大') !== -1 || errMsg.indexOf('超限') !== -1) {
          errMsg = '图片过大，请压缩后再上传';
        }
        results[idx] = '<div class="alert alert-danger mb-2">' + errMsg + '</div>';
      }
      uploading--;
      finished++;
      if (finished === files.length) {
        // 上传完成后，将广告位插入到链接卡片下方
        let html = results.join('');
        // if (window.config && window.config.ads_code && window.config.ads_code.trim()) {
        //   html += `<div class="mb-3" style="width:100%;text-align:center;">${window.config.ads_code}</div>`;
        // }
        uploadResult.innerHTML = html;
        // 隐藏上方广告位
        // var adsArea = document.getElementById('adsArea');
        // if (adsArea) adsArea.style.display = 'none';
      } else {
        next();
      }
    })
    .catch(() => {
      results[idx] = '<div class="alert alert-danger mb-2">图片过大，请压缩后再上传</div>';
      uploading--;
      finished++;
      if (finished === files.length) {
        let html = results.join('');
        // if (window.config && window.config.ads_code && window.config.ads_code.trim()) {
        //   html += `<div class="mb-3" style="width:100%;text-align:center;">${window.config.ads_code}</div>`;
        // }
        uploadResult.innerHTML = html;
        // var adsArea = document.getElementById('adsArea');
        // if (adsArea) adsArea.style.display = 'none';
      } else {
        next();
      }
    });
  }

  next();
}

// 表单提交（防止回车自动提交表单）
if (uploadForm) {
  uploadForm.addEventListener('submit', function(e) {
    e.preventDefault();
  });
}

// 点击“开始上传”按钮时上传（彻底禁止超限时上传）
if (startUploadBtn) {
  startUploadBtn.onclick = function() {
    if (!canUpload) {
      showToast('您已到达今日上传限制');
      return;
    }
    if (!selectedFiles || !selectedFiles.length) {
      showToast('请先选择图片');
      return;
    }
    uploadFiles(selectedFiles);
  };
}

// 文件选择后仅保存，不自动上传
fileInput.addEventListener('change', function() {
  selectedFiles = fileInput.files;
  if (isBlacklisted) {
    uploadResult.innerHTML = '<div class="alert alert-danger mb-2" style="font-weight:bold;">您的IP已被禁止上传</div>';
    fileInput.value = '';
    selectedFiles = null;
    return;
  }
  if (!canUpload) {
    uploadResult.innerHTML = '<div class="alert alert-danger mb-2" style="font-weight:bold;">您已到达今日上传限制</div>';
    fileInput.value = '';
    selectedFiles = null;
    return;
  }
  uploadResult.innerHTML = selectedFiles && selectedFiles.length
    ? `<div class="alert alert-primary mb-2" style="font-weight:bold;"><i class="bi bi-images me-2"></i>已选择 <span style="color:#1cc88a">${selectedFiles.length}</span> 张图片，点击 <span style="color:#4e73df;">“开始上传”</span> 按钮上传</div>`
    : '';
});

// 点击区域触发文件选择
pasteDropArea.addEventListener('click', function() {
  fileInput.click();
});

// 拖拽上传
pasteDropArea.addEventListener('dragover', function(e) {
  e.preventDefault();
  pasteDropArea.style.boxShadow = '0 0 0 0.2rem #4e73df33';
  pasteDropArea.style.background = '#eaf2ff';
});
pasteDropArea.addEventListener('dragleave', function(e) {
  e.preventDefault();
  pasteDropArea.style.boxShadow = '';
  pasteDropArea.style.background = '#f8f9fa';
});
pasteDropArea.addEventListener('drop', function(e) {
  e.preventDefault();
  pasteDropArea.style.boxShadow = '';
  pasteDropArea.style.background = '#f8f9fa';
  const files = Array.from(e.dataTransfer.files).filter(file => file.type.indexOf('image') === 0);
  if (files.length) uploadFiles(files);
});

// 粘贴上传
pasteDropArea.addEventListener('paste', function(e) {
  const items = (e.clipboardData || window.clipboardData).items;
  const files = [];
  for (let i = 0; i < items.length; i++) {
    const item = items[i];
    if (item.kind === 'file' && item.type.indexOf('image') === 0) {
      files.push(item.getAsFile());
    }
  }
  if (files.length) {
    uploadFiles(files);
    e.preventDefault();
  }
});

// 清除按钮
if (clearFilesBtn) {
  clearFilesBtn.onclick = function() {
    fileInput.value = '';
    uploadResult.innerHTML = '';
    if (uploadProgress) uploadProgress.style.display = 'none';
    if (progressInfo) progressInfo.style.display = 'none';
  };
}

// 显示图片信息
function showImageInfoHtml(url) {
  return `
    <div class="upload-image-card">
      <div class="upload-image-preview text-center">
        <img src="${url}" alt="预览" style="max-width:120px;max-height:120px;border-radius:14px;box-shadow:0 2px 12px #eaf2ff;object-fit:contain;border:1px solid #e3eaf5;background:#fff;">
      </div>
      <div class="upload-image-info w-100">
        <div class="input-group input-group-sm mb-2">
          <span class="input-group-text">直链</span>
          <input type="text" class="form-control" value="${url}" readonly id="direct_${encodeURIComponent(url)}">
          <button class="btn btn-outline-secondary" type="button" onclick="copyTextToast('${url}', 'direct_${encodeURIComponent(url)}')">复制</button>
        </div>
        <div class="input-group input-group-sm mb-2">
          <span class="input-group-text">Markdown</span>
          <input type="text" class="form-control" value="![LeisurePic](${url})" readonly id="md_${encodeURIComponent(url)}">
          <button class="btn btn-outline-secondary" type="button" onclick="copyTextToast('![LeisurePic](${url})', 'md_${encodeURIComponent(url)}')">复制</button>
        </div>
        <div class="input-group input-group-sm mb-2">
          <span class="input-group-text">HTML</span>
          <input type="text" class="form-control" value='<img src="${url}" alt="LeisurePic">' readonly id="html_${encodeURIComponent(url)}">
          <button class="btn btn-outline-secondary" type="button" onclick="copyTextToast('<img src=&quot;${url}&quot; alt=&quot;LeisurePic&quot;>', 'html_${encodeURIComponent(url)}')">复制</button>
        </div>
        <div class="d-flex gap-2 mt-2">
          <a href="${url}" target="_blank" class="btn btn-primary btn-sm flex-fill">查看图片</a>
          <button class="btn btn-danger btn-sm flex-fill" type="button" onclick="removePreview()">移除预览</button>
        </div>
      </div>
    </div>
  `;
}

// 工具函数
function copyTextToast(text, inputId) {
  // 优先使用 Clipboard API
  if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function' && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(() => showToast('已复制')).catch(() => {
      fallbackCopyText(text, inputId);
    });
  } else {
    fallbackCopyText(text, inputId);
  }
}

function fallbackCopyText(text, inputId) {
  // 兼容：选中 input 并执行 copy 命令
  let input = inputId ? document.getElementById(inputId) : null;
  if (!input) {
    input = document.createElement('input');
    input.value = text;
    document.body.appendChild(input);
  }
  input.readOnly = false;
  input.select();
  input.setSelectionRange(0, 99999);
  try {
    document.execCommand('copy');
    showToast('已复制');
  } catch (e) {
    showToast('请手动复制');
  }
  input.readOnly = true;
  if (!inputId) document.body.removeChild(input);
}
function showToast(msg) {
  const toast = document.getElementById('copiedToast');
  if (toast) {
    toast.innerText = msg;
    toast.style.display = 'block';
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => { toast.style.display = 'none'; }, 1200);
  }
}
function removePreview() {
  uploadResult.innerHTML = '';
}

// DOMContentLoaded 事件
window.addEventListener('DOMContentLoaded', function() {
  refreshUploadPermission();

  // 登录模态框
  var showLoginBtn = document.getElementById('showLoginBtn');
  if (showLoginBtn) {
    showLoginBtn.onclick = function(e) {
      e.preventDefault();
      var modal = new bootstrap.Modal(document.getElementById('loginModal'));
      modal.show();
    };
  }
  var loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.onsubmit = function(e) {
      e.preventDefault();
      var form = e.target;
      var formData = new FormData(form);
      var msgDiv = document.getElementById('loginMsg');
      msgDiv.innerHTML = '登录中...';
      fetch('app/login.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          msgDiv.innerHTML = '<span class="text-success">登录成功，正在刷新...</span>';
          setTimeout(function(){ location.reload(); }, 800);
        } else {
          msgDiv.innerHTML = '<span class="text-danger">' + (res.error || res.message || '登录失败') + '</span>';
        }
      })
      .catch(() => {
        msgDiv.innerHTML = '<span class="text-danger">登录失败，请重试</span>';
      });
    };
  }

  // 用户菜单下拉
  var userDropdown = document.getElementById('userDropdown');
  if (userDropdown) {
    userDropdown.addEventListener('click', function(e) {
      e.stopPropagation();
      userDropdown.classList.toggle('open');
    });
    // 点击菜单项时不关闭菜单（可选）
    var userMenu = document.getElementById('userMenu');
    if (userMenu) {
      userMenu.addEventListener('click', function(e) {
        e.stopPropagation();
      });
    }
    // 点击页面其他地方关闭菜单
    document.body.addEventListener('click', function(e) {
      if (userDropdown.classList.contains('open')) {
        userDropdown.classList.remove('open');
      }
    });
  }
});
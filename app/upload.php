<?php
require_once __DIR__ . '/../app/error_log.php';
require_once __DIR__ . '/../app/csrf.php';
ini_set('memory_limit', '256M'); // 或更大，根据需要调整
// ====== 新增接口：支持 action=check_limit，面 ======
if (isset($_GET['action']) && $_GET['action'] === 'check_limit') {
    session_start();
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../app/core.php';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!checkUploadPermission($ip)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => '今日上传次数已达上限'], JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

session_start();

function short_random_str($length = 6) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $result = '';
    for ($i = 0; $i < $length; $i++) {
        $result .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $result;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/core.php';
require_once __DIR__ . '/../app/image_compress.php';
require_once __DIR__ . '/../app/watermark.php';
require_once __DIR__ . '/../app/format_convert.php';
require_once __DIR__ . '/../app/anti_porn.php';
require_once __DIR__ . '/../app/bark_notify.php'; // 加载 bark_notify

header('Content-Type: application/json');

$config = include __DIR__ . '/../config/config.php';
$user = $_SESSION['user'] ?? 'guest';

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (in_array($ip, $config['ip_blacklist'])) {
    http_response_code(403);
    echo json_encode(['error' => '您的IP已被禁止上传']);
    exit;
}
if ($config['ip_whitelist'] && !in_array($ip, $config['ip_whitelist'])) {
    http_response_code(403);
    echo json_encode(['error' => '您的IP未被允许上传']);
    exit;
}

if (!checkUploadPermission($ip)) {
    http_response_code(429);
    echo json_encode(['error' => '今日上传次数已达上限']);
    exit;
}

// 优先使用配置中的上传目录，否则用默认目录
$uploadDir = '';
if (!empty($config['upload_dir'])) {
    // 支持绝对路径和相对路径
    $uploadDir = $config['upload_dir'];
    if (strpos($uploadDir, '/') !== 0 && strpos($uploadDir, ':') === false) {
        // 相对路径，基于当前目录
        $uploadDir = __DIR__ . '/../' . ltrim($uploadDir, '/\\');
    }
    $uploadDir = rtrim($uploadDir, '/\\') . '/' . date('Y/m/d');
} else {
    $uploadDir = __DIR__ . '/../public/uploads/' . date('Y/m/d');
}

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

function getUploadFiles() {
    if (isset($_FILES['file']['name']) && is_array($_FILES['file']['name'])) {
        $files = [];
        foreach ($_FILES['file']['name'] as $i => $name) {
            $files[] = [
                'name' => $_FILES['file']['name'][$i],
                'type' => $_FILES['file']['type'][$i],
                'tmp_name' => $_FILES['file']['tmp_name'][$i],
                'error' => $_FILES['file']['error'][$i],
                'size' => $_FILES['file']['size'][$i],
            ];
        }
        return $files;
    } elseif (isset($_FILES['file']['name'])) {
        return [[
            'name' => $_FILES['file']['name'],
            'type' => $_FILES['file']['type'],
            'tmp_name' => $_FILES['file']['tmp_name'],
            'error' => $_FILES['file']['error'],
            'size' => $_FILES['file']['size'],
        ]];
    }
    return [];
}

// 删除本文件 check_csrf_token_json，直接调用 csrf.php 提供的 check_csrf_token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_token($_POST['csrf_token'] ?? '');
}

$files = getUploadFiles();

if (!$files || !isset($files[0]['name'])) {
    http_response_code(400);
    echo json_encode(['error' => '没有上传文件']);
    exit;
}

$results = [];
$allowedExts = $config['allowed_types'] ?? ['jpg','jpeg','png','gif','webp','bmp','txt','pdf','doc','docx','mp4'];
$imageExts = ['jpg','jpeg','png','gif','webp','bmp'];
$minWidth = $config['min_width'] ?? 100;
$minHeight = $config['min_height'] ?? 100;
$maxSize = $config['max_upload_size'] ?? 5 * 1024 * 1024;
$enableAntiporn = !empty($config['enable_antiporn']);
$enableCompress = !empty($config['enable_compress']);
$imageQuality = $config['image_quality'] ?? 75;
$enableWatermark = !empty($config['enable_watermark']);
$watermarkImage = !empty($config['watermark_image']) && file_exists($config['watermark_image']) ? $config['watermark_image'] : '';
$watermarkText = !empty($config['watermark_text']) ? $config['watermark_text'] : '';
$enableFormatConvert = !empty($config['enable_format_convert']);
$defaultFormat = !empty($config['default_format']) ? $config['default_format'] : '';
$siteDomain = rtrim($config['site_domain'] ?? '', '/');
$urlPrefix = '/public/uploads/' . date('Y/m/d') . '/';

foreach ($files as $file) {

    if (!checkUploadPermission($ip)) {
        $results[] = ['success' => false, 'error' => '今日上传次数已达上限'];
        break;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $results[] = ['success' => false, 'error' => '上传失败，错误代码：' . $file['error']];
        continue;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) {
        $results[] = ['success' => false, 'error' => '不支持该文件格式'];
        continue;
    }

    // 用 fileinfo 检查实际MIME类型
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowedMimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp',
        'txt' => 'text/plain',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'mp4' => 'video/mp4',
    ];
    if (isset($allowedMimeTypes[$ext])) {
        if (strpos($mimeType, explode('/', $allowedMimeTypes[$ext])[0]) !== 0 && $mimeType !== $allowedMimeTypes[$ext]) {
            $results[] = ['success' => false, 'error' => '文件类型与扩展名不符'];
            continue;
        }
    }

    // ===== PDF 文件安全校验 =====
    if ($ext === 'pdf') {
        // 检查文件头是否为 %PDF-
        $fp = fopen($file['tmp_name'], 'rb');
        $header = fread($fp, 5);
        fclose($fp);
        if ($header !== '%PDF-') {
            $results[] = ['success' => false, 'error' => 'PDF 文件头无效，禁止上传伪造文件'];
            continue;
        }
        // 可选：简单检测是否包含脚本（如 /JavaScript）
        $content = file_get_contents($file['tmp_name'], false, null, 0, 4096); // 只读前4K
        if (stripos($content, '/JavaScript') !== false || stripos($content, '/JS') !== false) {
            $results[] = ['success' => false, 'error' => 'PDF 文件包含可疑脚本，禁止上传'];
            continue;
        }
    }

    // ===== MP4 文件安全校验 =====
    if ($ext === 'mp4') {
        // 检查文件头是否包含 ftyp
        $fp = fopen($file['tmp_name'], 'rb');
        $header = fread($fp, 12);
        fclose($fp);
        // ftyp 一般在第5-8字节
        if (strpos($header, 'ftyp') === false) {
            $results[] = ['success' => false, 'error' => 'MP4 文件头无效，禁止上传伪造文件'];
            continue;
        }
    }

    // 用 fileinfo 检查图片类型
    if (in_array($ext, $imageExts)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (strpos($mimeType, 'image/') !== 0) {
            $results[] = ['success' => false, 'error' => "文件不是有效图片"];
            continue;
        }
        // 优先用 getimagesize 获取尺寸
        $imgInfo = @getimagesize($file['tmp_name']);
        if ($imgInfo && isset($imgInfo[0], $imgInfo[1])) {
            $imgWidth = $imgInfo[0];
            $imgHeight = $imgInfo[1];
        } else {
            // getimagesize 失败再尝试 identify
            $cmd = 'identify -format "%w %h" ' . escapeshellarg($file['tmp_name']) . ' 2>&1';
            exec($cmd, $out, $ret);
            if ($ret !== 0 || empty($out[0])) {
                error_log('getimagesize & identify failed: ' . $file['tmp_name'] . ' output: ' . implode(' | ', $out));
                $results[] = ['success' => false, 'error' => "无法获取图片尺寸"];
                continue;
            }
            list($imgWidth, $imgHeight) = explode(' ', $out[0]);
        }
        if ($imgWidth < $minWidth || $imgHeight < $minHeight) {
            $results[] = ['success' => false, 'error' => "图片尺寸不能小于 {$minWidth}x{$minHeight}"];
            continue;
        }
    }

    if ($file['size'] > $maxSize) {
        $results[] = ['success' => false, 'error' => '文件过大'];
        continue;
    }

    $filename = short_random_str(6) . '.' . $ext;
    $destPath = $uploadDir . '/' . $filename;

    // 临时文件可能不是通过 HTTP 上传的，兼容部分手机端/小程序
    $moveResult = false;
    if (is_uploaded_file($file['tmp_name'])) {
        $moveResult = move_uploaded_file($file['tmp_name'], $destPath);
    } else {
        // fallback: 普通重命名/拷贝
        $moveResult = @rename($file['tmp_name'], $destPath);
        if (!$moveResult) {
            $moveResult = @copy($file['tmp_name'], $destPath);
            @unlink($file['tmp_name']);
        }
    }
    if (!$moveResult) {
        $errorMsg = '保存文件失败';
        if (!is_writable($uploadDir)) {
            $errorMsg .= '（目录不可写: ' . $uploadDir . '）';
        } elseif (!file_exists($file['tmp_name'])) {
            $errorMsg .= '（临时文件不存在: ' . $file['tmp_name'] . '）';
        }
        $results[] = ['success' => false, 'error' => $errorMsg];
        continue;
    }

    // ====== EXIF方向校正 ======
    if (in_array($ext, ['jpg', 'jpeg'])) {
        // 优先用PHP方式自动旋转
        $php_orient_ok = false;
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($destPath);
            if ($exif && isset($exif['Orientation']) && $exif['Orientation'] != 1) {
                $img = @imagecreatefromjpeg($destPath);
                if ($img) {
                    switch ($exif['Orientation']) {
                        case 3:
                            $img = imagerotate($img, 180, 0);
                            break;
                        case 6:
                            $img = imagerotate($img, -90, 0);
                            break;
                        case 8:
                            $img = imagerotate($img, 90, 0);
                            break;
                    }
                    imagejpeg($img, $destPath, 90);
                    imagedestroy($img);
                    $php_orient_ok = true;
                }
            } else {
                $php_orient_ok = true; // 无需旋转
            }
        }
        if (!$php_orient_ok) {
            // PHP方式失败再尝试mogrify
            $cmd = 'mogrify -auto-orient ' . escapeshellarg($destPath) . ' 2>/dev/null';
            exec($cmd, $out, $ret);
            if ($ret !== 0) {
                error_log('EXIF方向校正失败: ' . $destPath);
                // 不再中断上传，仅记录日志
            }
        }
    }

    // 色情检测
    if (in_array($ext, $imageExts) && $enableAntiporn && !antiPornCheck($destPath)) {
        unlink($destPath);
        $results[] = ['success' => false, 'error' => '上传文件包含违规内容'];
        continue;
    }

    // 压缩
    if (in_array($ext, ['jpg','jpeg','png']) && $enableCompress) {
        compressImage($destPath, $imageQuality);
    }

    // 水印
    if ($enableWatermark && in_array($ext, ['jpg','jpeg','png'])) {
        if (!empty($config['watermark_image'])) {
            addWatermark($destPath, $config['watermark_image']);
        } elseif ($watermarkText) {
            // 文字水印逻辑可扩展
        }
    }

    // 格式转换
    if (
        $enableFormatConvert &&
        $defaultFormat &&
        function_exists('convertFormat') &&
        strtolower($ext) !== strtolower($defaultFormat) // 只有当源格式和目标格式不一致时才转换
    ) {
        $newExt = $defaultFormat;
        $newPath = preg_replace('/\.[^.]+$/', '.' . $newExt, $destPath);
        if (convertFormat($destPath, $newPath)) {
            unlink($destPath);
            $destPath = $newPath;
            $filename = basename($newPath);
        } else {

        }
    }

    logUpload($user, $ip, $filename, @filesize($destPath));
    $url = $siteDomain ? $siteDomain . $urlPrefix . $filename : $urlPrefix . $filename;

    // 上传成功后推送 Bark 通知（如配置了 bark_key）
    if (!empty($config['bark_key'])) {
        $fileSize = @filesize($destPath);
        $uploadTime = date('Y-m-d H:i:s');
        $barkBody = "🖼️ 文件名: {$filename}\n"
            . "📦 大小: " . ($fileSize !== false ? number_format($fileSize / 1024, 2) . ' KB' : '未知') . "\n"
            . "🌐 上传IP: {$ip}\n"
            . "⏰ 上传时间: {$uploadTime}";
        bark_notify('✅ 图片上传成功 🎉', $barkBody, $url);
    }

    $results[] = [
        'success' => true,
        'message' => '上传成功',
        'url' => $url,
    ];
}

echo count($results) === 1 ? json_encode($results[0], JSON_UNESCAPED_UNICODE) : json_encode($results, JSON_UNESCAPED_UNICODE);
exit;

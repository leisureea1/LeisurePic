<?php
require_once __DIR__ . '/../app/error_log.php';

function addWatermark($filePath, $watermarkPath, $opacity = 0.6) {
    $info = getimagesize($filePath);
    // 兼容相对路径水印（只要不是盘符开头，都拼接到项目根目录）
    if (!preg_match('#^[a-zA-Z]:[\\/]#', $watermarkPath)) {
        $watermarkPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim($watermarkPath, '/\\');
    }
    // 日志准备
    $logFile = __DIR__ . '/../log/water.json';
    $logArr = [];
    if (file_exists($logFile)) {
        $logArr = json_decode(file_get_contents($logFile), true) ?: [];
    }
    $logEntry = [
        'time' => date('Y-m-d H:i:s'),
        'file' => $filePath,
        'watermark' => $watermarkPath,
        'result' => null,
        'error' => null,
        'final_watermark_path' => $watermarkPath
    ];
    if (!$info) {
        $logEntry['result'] = false;
        $logEntry['error'] = 'getimagesize failed';
        $logArr[] = $logEntry;
        file_put_contents($logFile, json_encode($logArr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        return false;
    }
    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($filePath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($filePath);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($filePath);
            break;
        default:
            $logEntry['result'] = false;
            $logEntry['error'] = 'unsupported mime: ' . $mime;
            $logArr[] = $logEntry;
            file_put_contents($logFile, json_encode($logArr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
            return false;
    }
    if (!file_exists($watermarkPath)) {
        $logEntry['result'] = false;
        $logEntry['error'] = 'watermark file not found';
        $logArr[] = $logEntry;
        file_put_contents($logFile, json_encode($logArr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        return false;
    }
    $wm = imagecreatefrompng($watermarkPath);
    if (!$wm) {
        $logEntry['result'] = false;
        $logEntry['error'] = 'imagecreatefrompng failed';
        $logArr[] = $logEntry;
        file_put_contents($logFile, json_encode($logArr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
        return false;
    }
    $iw = imagesx($image);
    $ih = imagesy($image);
    $targetW = round($iw / 10);
    $scale   = $targetW / imagesx($wm);
    $targetH = round(imagesy($wm) * $scale);
    $wmResized = imagecreatetruecolor($targetW, $targetH);
    imagealphablending($wmResized, false);
    imagesavealpha($wmResized, true);
    imagecopyresampled($wmResized, $wm, 0, 0, 0, 0, $targetW, $targetH, imagesx($wm), imagesy($wm));
    $x = $iw - $targetW - 10;
    $y = $ih - $targetH - 10;
    if ($opacity >= 1) {
        imagecopy($image, $wmResized, $x, $y, 0, 0, $targetW, $targetH);
    } else {
        // 透明度处理（效率较低，建议水印PNG自带透明度）
        for ($i = 0; $i < $targetW; $i++) {
            for ($j = 0; $j < $targetH; $j++) {
                $rgba = imagecolorat($wmResized, $i, $j);
                $a = ($rgba & 0x7F000000) >> 24;
                $a = 127 - (127 - $a) * $opacity;
                $color = imagecolorsforindex($wmResized, $rgba);
                $alphaColor = imagecolorallocatealpha($image, $color['red'], $color['green'], $color['blue'], $a);
                imagesetpixel($image, $x + $i, $y + $j, $alphaColor);
            }
        }
    }
    switch ($mime) {
        case 'image/jpeg':
            imagejpeg($image, $filePath, 90);
            break;
        case 'image/png':
            imagesavealpha($image, true);
            imagepng($image, $filePath);
            break;
        case 'image/gif':
            imagegif($image, $filePath);
            break;
    }
    imagedestroy($image);
    imagedestroy($wm);
    imagedestroy($wmResized);
    $logEntry['result'] = true;
    $logArr[] = $logEntry;
    file_put_contents($logFile, json_encode($logArr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    return true;
}

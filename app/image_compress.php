<?php
require_once __DIR__ . '/../app/error_log.php';

function compressImage($filePath, $quality = 75) {
    $info = getimagesize($filePath);
    if (!$info) return false;

    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($filePath);
            imagejpeg($image, $filePath, $quality);
            imagedestroy($image);
            break;
        case 'image/png':
            $image = imagecreatefrompng($filePath);
            // PNG 压缩等级是0-9，转换质量为压缩等级
            $pngQuality = (int)((100 - $quality) / 10);
            imagepng($image, $filePath, $pngQuality);
            imagedestroy($image);
            break;
        case 'image/gif':
            // GIF 不压缩，原样保留
            break;
        default:
            return false;
    }
    return true;
}
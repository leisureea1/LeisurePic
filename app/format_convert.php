<?php
require_once __DIR__ . '/../app/error_log.php';

function convertFormat($srcPath, $destPath) {
    ini_set('memory_limit', '256M'); 

    $info = getimagesize($srcPath);
    if (!$info) return false;

    $mime = $info['mime'];
    $width = $info[0];
    $height = $info[1];

    $maxDim = 2000; 
    $scale = min(1, $maxDim / max($width, $height));
    $newWidth = (int)($width * $scale);
    $newHeight = (int)($height * $scale);


    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($srcPath);
            if (!$image) {
                error_log('[format_convert] imagecreatefromjpeg failed: ' . $srcPath);
                return false;
            }
            break;
        case 'image/png':
            $image = imagecreatefrompng($srcPath);
            if (!$image) {
                error_log('[format_convert] imagecreatefrompng failed: ' . $srcPath);
                return false;
            }
            break;
        case 'image/gif':
            $image = imagecreatefromgif($srcPath);
            if (!$image) {
                error_log('[format_convert] imagecreatefromgif failed: ' . $srcPath);
                return false;
            }
            break;
        default:
            error_log('[format_convert] unsupported mime: ' . $mime . ' for ' . $srcPath);
            return false;
    }
    if (!$image) return false;

    
    if ($scale < 1) {
        $newImage = imagecreatetruecolor($newWidth, $newHeight);

       
        if ($mime === 'image/png' || $mime === 'image/gif') {
            imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127));
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }

        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $newImage;
    }

    
    $ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));

  
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $result = imagejpeg($image, $destPath, 90);
            break;
        case 'png':
            $result = imagepng($image, $destPath);
            break;
        case 'gif':
            $result = imagegif($image, $destPath);
            break;
        case 'webp':
            if (function_exists('imagewebp')) {
                $result = imagewebp($image, $destPath);
            } else {
                error_log('[format_convert] imagewebp not supported for ' . $destPath);
                imagedestroy($image);
                return false;
            }
            break;
        default:
            error_log('[format_convert] unsupported target ext: ' . $ext . ' for ' . $destPath);
            imagedestroy($image);
            return false;
    }

    imagedestroy($image);
    return $result;
}

<?php
// 自动更新脚本

set_time_limit(300);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$baseDir = dirname(__DIR__);
$versionFile = $baseDir . '/version.json';
$remoteVersionUrl = 'https://raw.githubusercontent.com/leisureea1/Leisure-/refs/heads/version/version.json'; // 远程版本描述文件
$tmpDir = $baseDir . '/.update_tmp_' . uniqid();
$tmpZip = $tmpDir . '/update.zip';

// 1. 获取远程版本信息
$remoteJson = @file_get_contents($remoteVersionUrl);
if (!$remoteJson) {
    exit('无法获取远程版本信息');
}
$remoteInfo = json_decode($remoteJson, true);
if (
    !$remoteInfo ||
    empty($remoteInfo['version']) ||
    empty($remoteInfo['update_url']) ||
    empty($remoteInfo['sha256'])
) {
    exit('远程版本信息格式错误');
}
$remoteVersion = $remoteInfo['version'];
$remoteZipUrl = $remoteInfo['update_url'];
$remoteSha256 = strtolower($remoteInfo['sha256']);

// 2. 获取本地版本号
$localVersion = '0.0.0';
if (file_exists($versionFile)) {
    $localInfo = json_decode(file_get_contents($versionFile), true);
    if ($localInfo && !empty($localInfo['version'])) {
        $localVersion = $localInfo['version'];
    }
}

// 3. 版本对比
if (version_compare($remoteVersion, $localVersion, '<=')) {
    exit('当前已是最新版本，无需更新');
}

// 4. 下载 update.zip
if (!is_dir($tmpDir)) mkdir($tmpDir, 0777, true);
$zipData = @file_get_contents($remoteZipUrl);
if (!$zipData) {
    rrmdir($tmpDir);
    exit('下载更新包失败');
}
file_put_contents($tmpZip, $zipData);

// 5. 校验sha256
$localSha256 = strtolower(hash_file('sha256', $tmpZip));
if ($localSha256 !== $remoteSha256) {
    rrmdir($tmpDir);
    exit('更新包校验失败，SHA256不一致');
}

// 6. 解压zip到临时目录
$unzipDir = $tmpDir . '/unzip';
if (!is_dir($unzipDir)) mkdir($unzipDir, 0777, true);
$zip = new ZipArchive();
if ($zip->open($tmpZip) !== TRUE) {
    rrmdir($tmpDir);
    exit('解压更新包失败');
}
$zip->extractTo($unzipDir);
$zip->close();
@unlink($tmpZip);

// 7. 递归复制（跳过 log、config.php、users.php）
function copyDirUpdate($src, $dst, $skip = []) {
    $dir = opendir($src);
    @mkdir($dst, 0777, true);
    while(false !== ($file = readdir($dir))) {
        if ($file == '.' || $file == '..') continue;
        if (in_array($file, $skip)) continue;
        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;
        if (is_dir($srcPath)) {
            // 跳过 log 目录
            if ($file === 'log') continue;
            copyDirUpdate($srcPath, $dstPath, $skip);
        } else {
            // 跳过 config.php、users.php do_update.php check_update.php
            if (in_array($file, ['config.php', 'users.php','do_update.php','check_update.php'])) continue;
            copy($srcPath, $dstPath);
        }
    }
    closedir($dir);
}

// 找到解压后的主目录（github zip通常有一级目录，普通zip可能没有）
$dirs = glob($unzipDir . '/*', GLOB_ONLYDIR);
$srcDir = $unzipDir;
if ($dirs && is_dir($dirs[0]) && file_exists($dirs[0] . '/config/new_config.php')) {
    $srcDir = $dirs[0];
}
copyDirUpdate($srcDir, $baseDir, ['log', 'config.php', 'users.php']);

// 8. 合并配置项
if (file_exists($srcDir . '/config/new_config.php') && file_exists($baseDir . '/config/config.php')) {
    require_once $baseDir . '/app/merge_config.php';
    // merge_config.php会自动合并 new_config.php 到 config.php
    merge_config($srcDir);
}

// 9. 更新本地版本号
$remoteTime = $remoteInfo['time'] ?? date('Y-m-d H:i:s');
file_put_contents($versionFile, json_encode(['version' => $remoteVersion, 'time' => $remoteTime], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

// 10. 清理临时目录
function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.','..']);
    foreach ($files as $file) {
        $path = "$dir/$file";
        if (is_dir($path)) rrmdir($path);
        else @unlink($path);
    }
    @rmdir($dir);
}
rrmdir($tmpDir);

echo '更新完成，当前版本：' . $remoteVersion;

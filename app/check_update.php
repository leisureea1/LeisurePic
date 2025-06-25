<?php
header('Content-Type: application/json');

// 本地版本
$versionFile = dirname(__DIR__) . '/version.json';
$localVersion = '0.0.0';
$localTime = '';
if (file_exists($versionFile)) {
    $info = json_decode(file_get_contents($versionFile), true);
    if ($info && !empty($info['version'])) {
        $localVersion = $info['version'];
        $localTime = $info['time'] ?? '';
    }
}

// 远程版本
$remoteVersionUrl = 'https://raw.githubusercontent.com/leisureea1/Leisure-/refs/heads/version/version.json';
$remoteJson = @file_get_contents($remoteVersionUrl);
if (!$remoteJson) {
    echo json_encode(['error' => '无法获取远程版本信息']);
    exit;
}
$remoteInfo = json_decode($remoteJson, true);
if (!$remoteInfo || empty($remoteInfo['version'])) {
    echo json_encode(['error' => '远程版本信息格式错误']);
    exit;
}
$remoteVersion = $remoteInfo['version'];
$updateUrl = $remoteInfo['update_url'] ?? '';
$desc = $remoteInfo['desc'] ?? '';
$remoteTime = $remoteInfo['time'] ?? '';

$result = [
    'local_version' => $localVersion,
    'local_time' => $localTime,
    'remote_version' => $remoteVersion,
    'remote_time' => $remoteTime,
    'has_update' => version_compare($remoteVersion, $localVersion, '>'),
    'update_url' => $updateUrl,
    'desc' => $desc
];

echo json_encode($result, JSON_UNESCAPED_UNICODE);

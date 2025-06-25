<?php
require_once __DIR__ . '/../app/error_log.php';
/**
 * 鉴黄检测函数，仅支持 Sightengine
 * 配置文件需有 enable_antiporn, sightengine_api_user, sightengine_api_secret
 * $filePath 为图片本地路径
 * 返回 true 表示安全，false 表示违规
 */
function antiPornCheck($filePath) {
    $configFile = __DIR__ . '/../config/config.php';
    $config = include $configFile;
    if (empty($config['enable_antiporn'])) {
        return true;
    }
    $models = $config['sightengine_models'] ?? 'nudity,wad';
    return sightengine_check($filePath, $config['sightengine_api_user'] ?? '', $config['sightengine_api_secret'] ?? '', $models);
}

/**
 * Sightengine API 鉴黄
 * @param string $filePath
 * @param string $apiUser
 * @param string $apiSecret
 * @param string $models
 * @return bool
 */
function sightengine_check($filePath, $apiUser, $apiSecret, $models = 'nudity,wad') {
    if (!$apiUser || !$apiSecret || !file_exists($filePath)) return true;
    $url = 'https://api.sightengine.com/1.0/check.json';
    $postFields = [
        'models' => $models,
        'api_user' => $apiUser,
        'api_secret' => $apiSecret,
    ];
    $postFields['media'] = new CURLFile($filePath);
    $logFile = __DIR__ . '/../log/anti_porn.json';
    $logArr = [];
    if (file_exists($logFile)) {
        $logArr = json_decode(file_get_contents($logFile), true) ?: [];
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $logEntry = [
        'time' => date('Y-m-d H:i:s'),
        'ip' => $ip,
        'request' => [
            'url' => $url,
            'fields' => array_merge($postFields, ['media' => basename($filePath)])
        ]
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);
    $logEntry['response'] = $response;
    $logEntry['curl_error'] = $curlError;
    $logArr[] = $logEntry;
    file_put_contents($logFile, json_encode($logArr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    if (!$response) return true;
    $result = json_decode($response, true);
    // 智能综合风险判断
    $configFile = __DIR__ . '/../config/config.php';
    $config = include $configFile;
    $threshold = isset($config['sightengine_risk_threshold']) ? floatval($config['sightengine_risk_threshold']) : 0.7;
    $riskFields = [
        ['nudity','raw'],
        ['weapon'],
        ['alcohol'],
        ['drugs'],
        ['offensive','prob'],
        ['scam','prob'],
        ['gore','prob']
    ];
    foreach ($riskFields as $field) {
        $val = $result;
        foreach ($field as $f) {
            if (isset($val[$f])) {
                $val = $val[$f];
            } else {
                $val = null;
                break;
            }
        }
        if (is_numeric($val) && $val > $threshold) return false;
    }
    return true;
}


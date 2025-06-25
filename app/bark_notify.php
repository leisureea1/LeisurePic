<?php
/**
 * 通过 Bark 推送通知到 iOS，并自动保存推送消息日志
 * @param string $title 通知标题
 * @param string $body  通知内容
 * @param string $url   可选，点击跳转链接
 * @return bool
 */
function bark_notify($title, $body, $url = '') {
    // 从全局配置读取 bark_key 和 bark_server
    $config = @include __DIR__ . '/../config/config.php';
    $bark_key = isset($config['bark_key']) ? trim($config['bark_key']) : '';
    $bark_server = isset($config['bark_server']) && $config['bark_server'] ? trim($config['bark_server']) : 'https://api.day.app';

    if (!$bark_key) return false;

    $params = [
        'title' => $title,
        'body' => $body,
        'isArchive' => 1, // 默认开启Bark的自动保存通知功能
    ];
    if ($url) $params['url'] = $url;

    $query = http_build_query($params);
    $api = rtrim($bark_server, '/') . '/' . $bark_key . '?' . $query;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    // 自动保存推送消息日志
    $logFile = __DIR__ . '/../log/bark_notify.json';
    $logArr = [];
    if (file_exists($logFile)) {
        $logArr = json_decode(file_get_contents($logFile), true) ?: [];
    }
    $logArr[] = [
        'time' => date('Y-m-d H:i:s'),
        'title' => $title,
        'body' => $body,
        'url' => $url,
        'api' => $api,
        'response' => $res,
        'error' => $err,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ];
    file_put_contents($logFile, json_encode($logArr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    if ($err) {
        error_log('[bark_notify] curl error: ' . $err);
        return false;
    }
    return true;
}

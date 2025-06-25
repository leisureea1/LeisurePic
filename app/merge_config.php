<?php
// 合并配置函数：将 fromDir/config/new_config.php 的新增项补充到 config/config.php，已有项不变

function merge_config($fromDir) {
    $configFile = __DIR__ . '/../config/config.php';
    $newConfigFile = rtrim($fromDir, '/\\') . '/config/new_config.php';

    // 1. 读取 config.php
    if (!file_exists($configFile)) {
        echo "config.php 不存在\n";
        return;
    }
    $config = include $configFile;
    if (!is_array($config)) {
        echo "config.php 格式错误\n";
        return;
    }

    // 2. 读取 new_config.php
    if (!file_exists($newConfigFile)) {
        echo "new_config.php 不存在\n";
        return;
    }
    $newConfig = include $newConfigFile;
    if (!is_array($newConfig)) {
        echo "new_config.php 格式错误\n";
        return;
    }

    // 3. 遍历 new_config.php，找出新增项
    $added = [];
    foreach ($newConfig as $k => $v) {
        if (!array_key_exists($k, $config)) {
            $config[$k] = $v;
            $added[$k] = $v;
        }
    }

    // 4. 如果有新增项，备份并写入
    if ($added) {
        // 5. 备份
        copy($configFile, $configFile . '.bak');
        // 6. 写入合并后的配置
        $content = "<?php\nreturn " . var_export($config, true) . ";\n";
        file_put_contents($configFile, $content);
        // 7. 输出合并项
        echo "已合并以下新配置项到 config.php:\n";
        foreach ($added as $k => $v) {
            echo "  - $k\n";
        }
    } else {
        echo "没有需要合并的新配置项，config.php 未做修改。\n";
    }

    // 删除 new_config.php
    if (file_exists($newConfigFile)) {
        @unlink($newConfigFile);
    }
}

// 兼容命令行调用
if (php_sapi_name() === 'cli' && isset($argv[1]) && is_dir($argv[1])) {
    merge_config($argv[1]);
}

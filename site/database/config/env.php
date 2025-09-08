<?php
if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void {
        if (!is_file($path) || !is_readable($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if ($line === '' || $line[0] === '#') continue;
            [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
            $k = trim($k);
            $v = trim($v, " \t\n\r\0\x0B\"'"); // strip quotes
            if ($k === '') continue;
            putenv("$k=$v");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
}

if (!function_exists('loadProjectEnv')) {
    function loadProjectEnv(): void {
        // Référence racine projet = /site
        $projectRoot = dirname(__DIR__, 2);
        // Choix du fichier selon l’hôte
        $isProd = isset($_SERVER['HTTP_HOST']) &&
            (stripos($_SERVER['HTTP_HOST'],'esig-sandbox.ch') !== false
                || stripos($_SERVER['HTTP_HOST'],'infomaniak') !== false);

        $envFile = $projectRoot . ($isProd ? '/.env.prod' : '/.env.local');
        if (!is_file($envFile)) {
            // fallback sur .env s’il existe
            $fallback = $projectRoot . '/.env';
            if (is_file($fallback)) $envFile = $fallback;
        }
        loadEnv($envFile);
    }
}

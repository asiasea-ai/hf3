<?php

declare(strict_types=1);

/**
 * 判断当前是否生产环境 —— APP_ENV=production(大小写不敏感) 视为生产
 *
 * APP_ENV 由 server.php 启动时 Symfony Dotenv 从 env/.env 加载进 $_ENV;
 * 缺省 'dev',默认非生产.
 */
function isProduction(): bool
{
    $env = strtolower((string) ($_ENV['APP_ENV'] ?? 'dev'));
    return $env === 'production';
}

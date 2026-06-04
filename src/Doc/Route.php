<?php

declare(strict_types=1);

/**
 * Doc 模块路由入口 —— 生产环境短路,其它环境注册 /__doc UI 和 /__doc/openapi.json spec.
 *
 * 由 config/routes.php require 进来,跑在 Hyperf DispatcherFactory 初始化阶段,
 * 此时 Router::$factory 已就位,可以安全注册.
 *
 * 路径不在 /web/ /app/ /rpc/ 业务 ring 下,JwtAuth 中间件自动放行(见 Hf3\Middleware\Util\Ring).
 */

use Hf3\Doc\Controller as DocController;
use Hyperf\HttpServer\Router\Router;

if (isProduction()) {
    return;
}

Router::get('/__doc', [DocController::class, 'ui']);
Router::get('/__doc/openapi.json', [DocController::class, 'spec']);

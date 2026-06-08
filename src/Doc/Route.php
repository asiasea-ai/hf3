<?php

declare(strict_types=1);

/**
 * Doc 模块路由入口 —— 非生产环境注册 /__doc UI 与 OpenAPI spec.
 */

use Hf3\Doc\Controller as DocController;
use Hyperf\HttpServer\Router\Router;

/** 由 config/routes.php require 进来,跑在 DispatcherFactory 初始化阶段,Router::$factory 已就位 */
if (isProduction()) {
    return;
}

/** 路径不在 /web/ /app/ /rpc/ 业务 ring 下,JwtAuth 中间件自动放行(见 Hf3\Middleware\Util\Ring) */
Router::get('/__doc', [DocController::class, 'ui']);
Router::get('/__doc/openapi.json', [DocController::class, 'spec']);

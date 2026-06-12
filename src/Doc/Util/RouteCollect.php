<?php

declare(strict_types=1);

namespace Hf3\Doc\Util;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\MiddlewareManager;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\HttpServer\Router\Handler;

/**
 * 从 Hyperf 已注册路由表里拉出所有 [method, path, ctrl, action, middlewares].
 */
final class RouteCollect
{
    /**
     * 收集指定 server 已注册的全部路由
     * @return array<int, array{
     *     method: string,
     *     path: string,
     *     placeholders: array<int, string>,
     *     controller: string,
     *     action: string,
     *     middlewares: array<int, string>
     * }>
     */
    public static function all(string $serverName = 'http'): array
    {
        $container = ApplicationContext::getContainer();
        $factory   = $container->get(DispatcherFactory::class);
        $collector = $factory->getRouter($serverName);

        /** 全局中间件不进 MiddlewareManager,从 config 取出与路由级合并 */
        $config = $container->get(ConfigInterface::class);
        $globalsRaw = (array) $config->get('middlewares.' . $serverName, []);
        $globals = array_filter($globalsRaw, 'is_string');
        $globals = array_values($globals);
        /** FastRoute getData() 返 [static, variable]:static=[method=>[path=>Handler]],variable=[method=>[{regex, routeMap:[id=>[Handler, vars]]}, ...]] */
        $data      = $collector->getData();

        $out = [];
        /** static segment */
        $static = $data[0] ?? [];
        if (is_array($static)) {
            foreach ($static as $method => $routes) {
                if (!is_array($routes)) {
                    continue;
                }
                foreach ($routes as $path => $handler) {
                    $row = RouteCollect::buildRow((string) $method, (string) $path, $handler, $globals);
                    if ($row !== null) {
                        $out[] = $row;
                    }
                }
            }
        }

        /** variable segment */
        $variable = $data[1] ?? [];
        if (is_array($variable)) {
            foreach ($variable as $method => $chunks) {
                if (!is_array($chunks)) {
                    continue;
                }
                foreach ($chunks as $chunk) {
                    if (!is_array($chunk) || !isset($chunk['routeMap'])) {
                        continue;
                    }
                    foreach ($chunk['routeMap'] as $entry) {
                        if (!is_array($entry) || !isset($entry[0])) {
                            continue;
                        }
                        $handler = $entry[0];
                        $path = $handler instanceof Handler ? (string) $handler->route : '';
                        if ($path === '') {
                            continue;
                        }
                        $row = RouteCollect::buildRow((string) $method, $path, $handler, $globals);
                        if ($row !== null) {
                            $out[] = $row;
                        }
                    }
                }
            }
        }
        return $out;
    }

    /**
     * 标准化一条路由
     * @param string $method
     * @param string $path
     * @param mixed $handler
     * @param array<int, string> $globals server 级全局中间件(caller 从 config 取好)
     * @return array<string, mixed>|null
     */
    private static function buildRow(string $method, string $path, mixed $handler, array $globals): ?array
    {
        if (!$handler instanceof Handler) {
            return null;
        }
        $callback = $handler->callback;
        if (!is_array($callback) || count($callback) !== 2) {
            return null;
        }
        [$ctrl, $action] = $callback;
        if (!is_string($ctrl) || !is_string($action)) {
            return null;
        }

        $placeholders = RouteCollect::extractPlaceholders($path);
        $routeMws     = MiddlewareManager::get('http', $path, strtoupper($method));
        $middlewares  = array_merge($globals, $routeMws);

        return [
            'method'       => strtoupper($method),
            'path'         => $path,
            'placeholders' => $placeholders,
            'controller'   => $ctrl,
            'action'       => $action,
            'middlewares'  => $middlewares,
        ];
    }

    /**
     * 从 `/account/{id:\d+}/foo` 抽出占位名 ['id']
     * @param string $route
     * @return array<int, string>
     */
    private static function extractPlaceholders(string $route): array
    {
        if (!preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)(?::[^}]+)?\}/u', $route, $m)) {
            return [];
        }
        return $m[1];
    }
}

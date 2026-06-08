<?php

declare(strict_types=1);

namespace Hf3\Middleware\Util;

use Hf3\Whitelist\Util\Cidr;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 限流决策工具：豁免判定 + 令牌桶清单构建，纯函数无副作用，consume 由中间件负责.
 */
final class Limiter
{
    /**
     * 取客户端 IP，优先 remote_addr，回退代理头，再回退本机
     * @param ServerRequestInterface $request
     * @return string
     */
    public static function clientIp(ServerRequestInterface $request): string
    {
        $params = $request->getServerParams();
        return (string) (
            $params['remote_addr']
            ?? $params['x-real-ip']
            ?? $params['x-forwarded-for']
            ?? '127.0.0.1'
        );
    }

    /**
     * 是否豁免限流：IP 白名单（CIDR）或路径白名单命中即豁免
     * @param array $ipConfig limiter.ip 配置
     * @param string $ip
     * @param string $path
     * @return bool
     */
    public static function exempt(array $ipConfig, string $ip, string $path): bool
    {
        $pathWhitelist = (array) ($ipConfig['path_whitelist'] ?? []);
        if (in_array($path, $pathWhitelist, true)) {
            return true;
        }

        $ipWhitelist = (array) ($ipConfig['ip_whitelist'] ?? []);
        foreach ($ipWhitelist as $cidr) {
            $hit = Cidr::contains((string) $cidr, $ip);
            if ($hit) {
                return true;
            }
        }

        return false;
    }

    /**
     * 构建按序施加的令牌桶清单：全站总量 → IP → 路由，qps<=0 的档位跳过
     * @param array $globalConfig limiter.global 配置
     * @param array $ipConfig limiter.ip 配置
     * @param array $routeConfig limiter.route 配置
     * @param string $ip
     * @param string $path
     * @return array<int, array{key: string, qps: int}>
     */
    public static function buckets(array $globalConfig, array $ipConfig, array $routeConfig, string $ip, string $path): array
    {
        $list = [];

        $globalQps = (int) ($globalConfig['global_qps'] ?? 0);
        if ($globalQps > 0) {
            $list[] = ['key' => 'limiter:global', 'qps' => $globalQps];
        }

        $ipQps = (int) ($ipConfig['ip_qps'] ?? 0);
        if ($ipQps > 0) {
            $list[] = ['key' => 'limiter:ip:' . $ip, 'qps' => $ipQps];
        }

        $overrides  = (array) ($routeConfig['overrides'] ?? []);
        $defaultQps = (int) ($routeConfig['default_qps'] ?? 0);
        $routeQps   = array_key_exists($path, $overrides) ? (int) $overrides[$path] : $defaultQps;
        if ($routeQps > 0) {
            $list[] = ['key' => 'limiter:route:' . $path . ':' . $ip, 'qps' => $routeQps];
        }

        return $list;
    }
}

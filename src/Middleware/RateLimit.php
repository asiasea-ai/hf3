<?php

declare(strict_types=1);

namespace Hf3\Middleware;

use Hf3\Whitelist\Util\Cidr;
use Hyperf\HttpServer\Response;
use Hyperf\RateLimit\Handler\RateLimitHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RateLimit implements MiddlewareInterface
{
    public function __construct(private readonly RateLimitHandler $handler)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip   = $this->clientIp($request);
        $path = $request->getUri()->getPath();

        $ipConfig    = (array) etc('limiter.ip', []);
        $routeConfig = (array) etc('limiter.route', []);

        // 1) IP 白名单豁免
        if ($this->ipInWhitelist($ip, $ipConfig)) {
            return $handler->handle($request);
        }

        // 2) 路径白名单豁免
        $pathWhitelist = (array) ($ipConfig['path_whitelist'] ?? []);
        if (in_array($path, $pathWhitelist, true)) {
            return $handler->handle($request);
        }

        // 3) IP 全局 QPS —— 令牌桶
        $ipQps = (int) ($ipConfig['ip_qps'] ?? 0);
        if ($ipQps > 0) {
            $bucket = $this->handler->build('limiter:ip:' . $ip, $ipQps, $ipQps, 0);
            if (!$bucket->consume(1)) {
                return $this->tooMany();
            }
        }

        // 4) 路由 QPS —— 令牌桶,含 overrides, 0 则跳过
        $overrides  = (array) ($routeConfig['overrides'] ?? []);
        $defaultQps = (int) ($routeConfig['default_qps'] ?? 0);
        $routeQps   = array_key_exists($path, $overrides) ? (int) $overrides[$path] : $defaultQps;
        if ($routeQps > 0) {
            $bucket = $this->handler->build('limiter:route:' . $path . ':' . $ip, $routeQps, $routeQps, 0);
            if (!$bucket->consume(1)) {
                return $this->tooMany();
            }
        }

        return $handler->handle($request);
    }

    private function ipInWhitelist(string $ip, array $ipConfig): bool
    {
        $whitelist = (array) ($ipConfig['ip_whitelist'] ?? []);
        foreach ($whitelist as $cidr) {
            if (Cidr::contains((string) $cidr, $ip)) {
                return true;
            }
        }
        return false;
    }

    private function clientIp(ServerRequestInterface $request): string
    {
        $params = $request->getServerParams();
        return (string) (
            $params['remote_addr']
            ?? $params['x-real-ip']
            ?? $params['x-forwarded-for']
            ?? '127.0.0.1'
        );
    }

    private function tooMany(): ResponseInterface
    {
        return (new Response())->withStatus(429)->json([
            'code'    => 429,
            'message' => 'Too Many Requests',
        ]);
    }
}

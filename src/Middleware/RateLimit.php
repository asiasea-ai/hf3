<?php

declare(strict_types=1);

namespace Hf3\Middleware;

use Hf3\Middleware\Util\Limiter;
use Hyperf\HttpServer\Response;
use Hyperf\RateLimit\Handler\RateLimitHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 全局兜底限流：白名单豁免后按序施加 全站总量 → IP → 路由 三档令牌桶，任一档耗尽即 429.
 */
final class RateLimit implements MiddlewareInterface
{
    public function __construct(private readonly RateLimitHandler $handler)
    {
    }

    /**
     * 全局限流入口，桶存 Redis 跨 worker/节点共享
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip   = Limiter::clientIp($request);
        $path = $request->getUri()->getPath();

        $globalConfig = (array) etc('limiter.global', []);
        $ipConfig     = (array) etc('limiter.ip', []);
        $routeConfig  = (array) etc('limiter.route', []);

        $exempt = Limiter::exempt($ipConfig, $ip, $path);
        if ($exempt) {
            return $handler->handle($request);
        }

        $buckets = Limiter::buckets($globalConfig, $ipConfig, $routeConfig, $ip, $path);
        foreach ($buckets as $spec) {
            $bucket = $this->handler->build($spec['key'], $spec['qps'], $spec['qps'], 0);
            $passed = $bucket->consume(1);
            if (!$passed) {
                return $this->tooMany();
            }
        }

        return $handler->handle($request);
    }

    /**
     * 限流命中响应 429
     * @return ResponseInterface
     */
    private function tooMany(): ResponseInterface
    {
        return (new Response())->withStatus(429)->json([
            'code'    => 429,
            'message' => 'Too Many Requests',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Hf3\Middleware;

use Hf3\Context\Request;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 请求上下文初始化 —— 链首播种 trace_id 与起始时刻,供信封与日志全程取用.
 */
final class Trace implements MiddlewareInterface
{
    /**
     * 在任何业务/鉴权/限流中间件之前初始化请求上下文
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        Request::start();

        return $handler->handle($request);
    }
}

<?php

declare(strict_types=1);

namespace Hf3\Throwable\Trigger;

use Hyperf\Context\Context;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Psr\Http\Message\ServerRequestInterface;
use Swow\Psr7\Message\ResponsePlusInterface;
use Throwable;

/**
 * 全局 HTTP 异常 handler —— 统一输出 JSON envelope.
 */
final class HttpHandler extends ExceptionHandler
{
    public function handle(Throwable $throwable, ResponsePlusInterface $response): ResponsePlusInterface
    {
        $request = Context::get(ServerRequestInterface::class);
        $result  = Http::run($throwable, $request, $response);

        $this->stopPropagation();

        return $result instanceof ResponsePlusInterface ? $result : $response;
    }

    public function isValid(Throwable $throwable): bool
    {
        return true;
    }
}

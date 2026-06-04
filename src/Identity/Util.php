<?php

declare(strict_types=1);

namespace Hf3\Identity;

use Hf3\Code\Code;
use Hf3\Throwable\Exception\WarnException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 请求路径 ring 守卫 —— 校验当前请求路径属于该 client 注册的 ring(自签模式用).
 */
final class Util
{
    /**
     * 校验请求路径属于该 client 的 ring
     * @param array $entry 注册项(含 ring/client_id)
     * @param ServerRequestInterface $request
     * @return void
     * @throws WarnException
     */
    public static function verify(array $entry, ServerRequestInterface $request): void
    {
        $ring     = (string) ($entry['ring'] ?? '');
        $path     = $request->getUri()->getPath();
        $clientId = (string) ($entry['client_id'] ?? '');
        if ($ring === '' || ($path !== $ring && !str_starts_with($path, $ring . '/'))) {
            throw new WarnException(code: Code::AUTH_FAILED, message: "请求路径 {$path} 不属于 client {$clientId} 的 ring {$ring}");
        }
    }
}

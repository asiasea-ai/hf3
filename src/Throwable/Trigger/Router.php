<?php

declare(strict_types=1);

namespace Hf3\Throwable\Trigger;

use Hf3\Code\Code;
use Hf3\Throwable\Exception\ErrorException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Router
{
    /**
     * 路由未找到
     * @param ServerRequestInterface $request
     * @return never
     */
    public static function notFound(ServerRequestInterface $request): never
    {
        throw new ErrorException(
            code: Code::ACTION_NOT_FOUND,
            message: 'Route not found: ' . $request->getMethod() . ' ' . $request->getUri()->getPath(),
            category: 'allow',
            httpStatus: 404,
        );
    }

    /**
     * 方法不允许
     * @param ServerRequestInterface $request
     * @return never
     */
    public static function methodNotAllowed(ServerRequestInterface $request): never
    {
        throw new ErrorException(
            code: Code::METHOD_NOT_ALLOWED,
            message: 'Method not allowed: ' . $request->getMethod() . ' ' . $request->getUri()->getPath(),
            category: 'allow',
            httpStatus: 405,
        );
    }

    /**
     * 安装路由触发器
     * @param object|null $unused
     * @return void
     */
    public static function install(?object $unused = null): void
    {

        unset($unused);
    }

    /**
     * 生成路由处理器
     * @param Code $code
     * @param int $httpStatus
     * @param string $prefix
     * @return \Closure
     */
    public static function handler(Code $code, int $httpStatus, string $prefix): \Closure
    {
        return static function (ServerRequestInterface $request, ResponseInterface $response) use ($code, $httpStatus, $prefix): void {
            unset($response);
            throw new ErrorException(
                code: $code,
                message: $prefix . ': ' . $request->getMethod() . ' ' . $request->getUri()->getPath(),
                category: 'allow',
                httpStatus: $httpStatus,
            );
        };
    }
}

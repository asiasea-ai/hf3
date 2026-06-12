<?php

declare(strict_types=1);

namespace Hf3\Middleware;

use Hf3\Code\Code;
use Hf3\Context\Request as Ctx;
use Hf3\Policy\Gate;
use Hf3\Throwable\Exception\AuthException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 会话鉴权中间件基类 —— 按 ring 分别挂载,各子类只守自己的 ring(ring()),验签策略由 identify() 实现.
 */
abstract class Auth implements MiddlewareInterface
{
    public function __construct(private readonly Gate $gate)
    {
    }

    /**
     * 命中本中间件 ring 的请求验签并写入身份,其它 ring / 非业务路径 / 白名单直接放行
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path   = $request->getUri()->getPath();
        $method = $request->getMethod();

        /** 只守本中间件负责的 ring,其它 ring / 非业务路径放行 */
        if (!str_starts_with($path, $this->ring())) {
            return $handler->handle($request);
        }

        /** 命中白名单放行 */
        if ($this->gate->forUser(null)->allows('oidc.bypass', [$method, $path])) {
            return $handler->handle($request);
        }

        /** 写入身份对象 */
        $identity  = $this->identify($request);
        Ctx::setIdentity((object) $identity);

        /** 写入账号 ID */
        $accountId = $identity['account_id'] ?? null;
        if (superEmpty($accountId)) {
            throw AuthException::fromCode(Code::AUTH_FAILED);
        }
        Ctx::setAccountId($accountId);

        /** 写入主体 ID */
        $companyId = (int) ($identity['company_id'] ?? null);
        if (superEmpty($companyId)) {
            throw AuthException::fromCode(Code::AUTH_COMPANY_ID_FAILED);
        }
        Ctx::setCompanyId($companyId);

        return $handler->handle($request);
    }

    /**
     * 本中间件负责的业务 ring 前缀(如 '/web/'),只有命中前缀的请求才鉴权
     * @return string
     */
    abstract protected function ring(): string;

    /**
     * 验证请求里的会话 token 并返回原始 claims —— 各 ring 直接调自己的验签工具实现
     * @param ServerRequestInterface $request
     * @return array
     */
    abstract protected function identify(ServerRequestInterface $request): array;
}

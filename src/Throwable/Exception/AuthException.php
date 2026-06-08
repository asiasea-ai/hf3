<?php

declare(strict_types=1);

namespace Hf3\Throwable\Exception;

use Hf3\Code\CodeInterface;
use Hf3\Throwable\AbstractException;
use Hf3\Throwable\Enum\Level;

/**
 * 鉴权异常 —— 登录失败、token 无效、权限不足.
 */
final class AuthException extends AbstractException
{
    private const string DEFAULT_CATEGORY = 'auth';

    /**
     * 按业务码构造鉴权异常,沿用默认 HTTP 状态码
     *
     * @param CodeInterface $code 业务码
     * @param string|null $message 自定义提示文案,为空时使用业务码默认文案
     * @param string|null $category 异常分类,为空时使用默认分类 auth
     * @return self
     */
    public static function fromCode(
        CodeInterface $code,
        ?string $message = null,
        ?string $category = null,
    ): self {
        return new self(
            code: $code,
            message: $message,
            category: $category ?? AuthException::DEFAULT_CATEGORY,
        );
    }

    /**
     * 按业务码构造鉴权异常并显式指定 HTTP 状态码
     *
     * @param CodeInterface $code 业务码
     * @param int $httpStatus 指定的 HTTP 状态码
     * @param string|null $message 自定义提示文案,为空时使用业务码默认文案
     * @param string|null $category 异常分类,为空时使用默认分类 auth
     * @return self
     */
    public static function withHttpStatus(
        CodeInterface $code,
        int $httpStatus,
        ?string $message = null,
        ?string $category = null,
    ): self {
        return new self(
            code: $code,
            message: $message,
            category: $category ?? AuthException::DEFAULT_CATEGORY,
            httpStatus: $httpStatus,
        );
    }

    public function level(): Level
    {
        return Level::Warning;
    }
}

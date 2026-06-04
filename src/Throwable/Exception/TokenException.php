<?php

declare(strict_types=1);

namespace Hf3\Throwable\Exception;

use Hf3\Code\CodeInterface;
use Hf3\Throwable\AbstractException;
use Hf3\Throwable\Enum\Level;

/**
 * Token 异常 —— 验签失败 / iss 不匹配 / aud 不匹配 / 已过期.
 *
 * level 默认 Warning (HTTP 400), category 默认 'token'.
 */
final class TokenException extends AbstractException
{
    private const string DEFAULT_CATEGORY = 'token';

    public static function fromCode(
        CodeInterface $code,
        ?string $message = null,
        ?string $category = null,
    ): self {
        return new self(
            code: $code,
            message: $message,
            category: $category ?? TokenException::DEFAULT_CATEGORY,
        );
    }

    public static function withHttpStatus(
        CodeInterface $code,
        int $httpStatus,
        ?string $message = null,
        ?string $category = null,
    ): self {
        return new self(
            code: $code,
            message: $message,
            category: $category ?? TokenException::DEFAULT_CATEGORY,
            httpStatus: $httpStatus,
        );
    }

    public function level(): Level
    {
        return Level::Warning;
    }
}

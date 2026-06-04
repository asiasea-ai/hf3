<?php

declare(strict_types=1);

namespace Hf3\Throwable\Exception;

use Hf3\Code\CodeInterface;
use Hf3\Throwable\AbstractException;
use Hf3\Throwable\Enum\Level;

/**
 * IAM 通信异常 —— JWKS 拉取失败 / token endpoint 不可达 / 配置缺失.
 *
 * level 默认 Error (HTTP 500), category 默认 'iam'.
 */
final class IamException extends AbstractException
{
    private const string DEFAULT_CATEGORY = 'iam';

    public static function fromCode(
        CodeInterface $code,
        ?string $message = null,
        ?string $category = null,
    ): self {
        return new self(
            code: $code,
            message: $message,
            category: $category ?? IamException::DEFAULT_CATEGORY,
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
            category: $category ?? IamException::DEFAULT_CATEGORY,
            httpStatus: $httpStatus,
        );
    }

    public function level(): Level
    {
        return Level::Error;
    }
}

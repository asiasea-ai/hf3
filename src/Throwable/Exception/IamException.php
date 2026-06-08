<?php

declare(strict_types=1);

namespace Hf3\Throwable\Exception;

use Hf3\Code\CodeInterface;
use Hf3\Throwable\AbstractException;
use Hf3\Throwable\Enum\Level;

/**
 * IAM 通信异常 —— JWKS 拉取失败 / token endpoint 不可达 / 配置缺失.
 */
final class IamException extends AbstractException
{
    private const string DEFAULT_CATEGORY = 'iam';

    /**
     * 按业务码构造 IAM 异常,沿用默认 HTTP 状态码
     *
     * @param CodeInterface $code 业务码
     * @param string|null $message 自定义提示文案,为空时使用业务码默认文案
     * @param string|null $category 异常分类,为空时使用默认分类 iam
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
            category: $category ?? IamException::DEFAULT_CATEGORY,
        );
    }

    /**
     * 按业务码构造 IAM 异常并显式指定 HTTP 状态码
     *
     * @param CodeInterface $code 业务码
     * @param int $httpStatus 指定的 HTTP 状态码
     * @param string|null $message 自定义提示文案,为空时使用业务码默认文案
     * @param string|null $category 异常分类,为空时使用默认分类 iam
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
            category: $category ?? IamException::DEFAULT_CATEGORY,
            httpStatus: $httpStatus,
        );
    }

    public function level(): Level
    {
        // 默认 Error 级别（对应 HTTP 500），IAM 不可达视为服务端故障
        return Level::Error;
    }
}

<?php

declare(strict_types=1);

namespace Hf3\Throwable\Util;

use Hf3\Code\Code;
use Hf3\Throwable\AbstractException;
use Hyperf\HttpMessage\Exception\HttpException;
use Throwable;

/**
 * 异常 → HTTP 业务码 / 状态码 / 友好 msg 映射.
 */
final class Mapping
{
    /**
     * 计算异常对应的 HTTP 状态码
     *
     * @param Throwable $throwable
     * @return int
     */
    public static function statusOf(Throwable $throwable): int
    {
        if ($throwable instanceof AbstractException) {
            // 显式指定的 httpStatus 优先
            if ($throwable->httpStatus !== null) {
                return $throwable->httpStatus;
            }
            $code = (int) $throwable->getCode();
            // 未登录 / 登录失败单独抛 401，让前端拦截器识别登录态
            if ($code === Code::AUTH_REQUIRED->value || $code === Code::AUTH_FAILED->value) {
                return 401;
            }
            // 其余业务异常一律 200，由 envelope code 区分成败
            return 200;
        }
        if ($throwable instanceof HttpException) {
            // Hyperf 路由层异常（404/405 等）保留自身状态码
            return $throwable->getStatusCode();
        }
        return 200;
    }

    /**
     * 将异常映射为 (业务码, 友好 msg) 二元组
     *
     * @param Throwable $throwable
     * @return array{0:int, 1:string}
     */
    public static function codeAndMessage(Throwable $throwable): array
    {
        // 业务异常：直接用自身 code + message
        if ($throwable instanceof AbstractException) {
            return [$throwable->getCode(), $throwable->getMessage()];
        }

        // Hyperf HttpException（404/405/...）：映射到 Hf3 业务码 + 中文友好 msg
        if ($throwable instanceof HttpException) {
            return match ($throwable->getStatusCode()) {
                404     => [Code::ACTION_NOT_FOUND->value, '接口不存在'],
                405     => [Code::METHOD_NOT_ALLOWED->value, '请求方法不被允许'],
                default => [Code::INTERNAL_ERROR->value, $throwable->getMessage() ?: '请求处理失败'],
            };
        }

        // 其它未知异常：生产隐藏 file:line（防 vendor 路径泄漏），dev 拼上方便调试
        $rawMsg = $throwable->getMessage();
        $msg = isProduction()
            ? '系统内部错误'
            : sprintf('%s at %s:%d', $rawMsg, $throwable->getFile(), $throwable->getLine());
        return [Code::INTERNAL_ERROR->value, $msg];
    }
}

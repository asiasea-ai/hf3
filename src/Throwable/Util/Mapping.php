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
     * 获取 HTTP 状态码 —— 业务异常一律 200(由 envelope code 区分成败),
     * AUTH_REQUIRED / AUTH_FAILED 单独抛 401 让前端拦截器识别登录态;
     * AbstractException 显式 httpStatus 仍然优先,Hyperf HttpException(404/405 等路由层异常)保留自身状态码
     * @param Throwable $throwable
     * @return int
     */
    public static function statusOf(Throwable $throwable): int
    {
        if ($throwable instanceof AbstractException) {
            if ($throwable->httpStatus !== null) {
                return $throwable->httpStatus;
            }
            $code = (int) $throwable->getCode();
            if ($code === Code::AUTH_REQUIRED->value || $code === Code::AUTH_FAILED->value) {
                return 401;
            }
            return 200;
        }
        if ($throwable instanceof HttpException) {
            return $throwable->getStatusCode();
        }
        return 200;
    }

    /**
     * 异常 → (业务码, 友好 msg) —— 三类分支:
     *   - 业务异常(AbstractException):用自身 code + message
     *   - Hyperf HttpException(404/405/...):映射到 Hf3 业务码 + 中文友好 msg
     *   - 其它未知异常:生产隐藏 file:line(防 vendor 路径泄漏),dev 拼上方便调试
     * @param Throwable $throwable
     * @return array{0:int, 1:string}
     */
    public static function codeAndMessage(Throwable $throwable): array
    {
        if ($throwable instanceof AbstractException) {
            return [$throwable->getCode(), $throwable->getMessage()];
        }

        if ($throwable instanceof HttpException) {
            return match ($throwable->getStatusCode()) {
                404     => [Code::ACTION_NOT_FOUND->value, '接口不存在'],
                405     => [Code::METHOD_NOT_ALLOWED->value, '请求方法不被允许'],
                default => [Code::INTERNAL_ERROR->value, $throwable->getMessage() ?: '请求处理失败'],
            };
        }

        $rawMsg = $throwable->getMessage();
        $msg = isProduction()
            ? '系统内部错误'
            : sprintf('%s at %s:%d', $rawMsg, $throwable->getFile(), $throwable->getLine());
        return [Code::INTERNAL_ERROR->value, $msg];
    }
}

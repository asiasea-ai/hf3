<?php

declare(strict_types=1);

namespace Hf3\Throwable\Util;

use Hf3\Throwable\Enum\Level;
use Psr\Log\LogLevel;

/**
 * Hf3 异常级别 / PHP 错误码 → PSR LogLevel 映射工具.
 */
final class PsrLevel
{
    /**
     * 异常级别 → PSR LogLevel
     * @param Level $level
     * @return string
     */
    public static function ofLevel(Level $level): string
    {
        return match ($level) {
            Level::Info    => LogLevel::INFO,
            Level::Notice  => LogLevel::NOTICE,
            Level::Warning => LogLevel::WARNING,
            Level::Error   => LogLevel::ERROR,
        };
    }

    /**
     * PHP 错误码 → PSR LogLevel
     * @param int $errorCode
     * @return string
     */
    public static function ofErrorCode(int $errorCode): string
    {
        return match ($errorCode) {
            E_PARSE, E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR
                => LogLevel::ERROR,
            E_WARNING, E_USER_WARNING, E_COMPILE_WARNING, E_RECOVERABLE_ERROR
                => LogLevel::WARNING,
            E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED
                => LogLevel::NOTICE,
            default
                => LogLevel::INFO,
        };
    }
}

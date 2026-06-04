<?php

declare(strict_types=1);

namespace Hf3\Throwable\Trigger;

use Hf3\Throwable\Util\HyperfLogger;
use Psr\Log\LogLevel;

/**
 * 进程 shutdown 时捕获 PHP 致命错误,写日志或 STDERR.
 */
final class Shutdown
{
    private const array FATAL_TYPES = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
        E_USER_ERROR,
    ];

    private const string LOG_CATEGORY = 'fatal';

    /**
     * 检查致命错误 —— register_shutdown_function 回调入口
     * @return void
     */
    public static function run(): void
    {
        Shutdown::runWithError(error_get_last());
    }

    /**
     * 处理指定错误
     * @param array|null $error
     * @return void
     */
    public static function runWithError(?array $error): void
    {
        if ($error === null || !in_array($error['type'], Shutdown::FATAL_TYPES, true)) {
            return;
        }

        $line = "{$error['message']} at file:{$error['file']} line:{$error['line']}";

        $logger = HyperfLogger::get();
        if ($logger !== null) {
            $logger->log(LogLevel::ERROR, $line, ['category' => Shutdown::LOG_CATEGORY]);
            return;
        }

        @fwrite(STDERR, '[fatal] ' . $line . PHP_EOL);
    }
}

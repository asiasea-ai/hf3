<?php

declare(strict_types=1);

namespace Hf3\Throwable;

use Hf3\Throwable\Enum\Level;
use Hf3\Throwable\Util\PsrLevel;
use Hyperf\Context\ApplicationContext;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * 异常 / 错误上报触发器 —— 写日志按级别分流.
 */
final class Trigger
{
    private readonly LoggerInterface $logger;

    /**
     * 触发构造
     * @param LoggerInterface|null $logger
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        if ($logger !== null) {
            $this->logger = $logger;
            return;
        }

        $factory = ApplicationContext::getContainer()->get(LoggerFactory::class);
        $this->logger = $factory->get('hyperf', 'default');
    }

    /**
     * 处理可抛出异常
     * @param \Throwable $throwable
     * @return void
     */
    public function throwable(\Throwable $throwable): void
    {
        $message = "{$throwable->getMessage()} at file:{$throwable->getFile()} line:{$throwable->getLine()}";

        if (!$throwable instanceof AbstractException) {
            $this->emit($message, LogLevel::ERROR, 'unknown');
            return;
        }

        $level = $throwable->level();
        if ($level === Level::Info) {
            return;
        }

        $this->emit(
            $message,
            PsrLevel::ofLevel($level),
            $throwable->category ?? 'exception',
        );
    }

    /**
     * 处理错误
     * @param mixed $msg
     * @param int $errorCode
     * @param array|null $location
     * @return void
     */
    public function error($msg, int $errorCode = E_USER_ERROR, ?array $location = null): void
    {
        if ($location === null) {
            $caller = debug_backtrace()[0] ?? [];
            $location = [
                'file' => (string) ($caller['file'] ?? ''),
                'line' => (int) ($caller['line'] ?? 0),
            ];
        }

        $file = (string) ($location['file'] ?? '');
        $line = (int) ($location['line'] ?? 0);

        $this->emit(
            "{$msg} at file:{$file} line:{$line}",
            PsrLevel::ofErrorCode($errorCode),
            'error',
        );
    }

    /**
     * 写入日志
     * @param string $message
     * @param string $logLevel
     * @param string $category
     * @return void
     */
    private function emit(string $message, string $logLevel, string $category): void
    {
        $this->logger->log($logLevel, $message, ['category' => $category]);
    }
}

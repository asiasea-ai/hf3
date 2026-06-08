<?php

declare(strict_types=1);

namespace Hf3\Crontab;

use Hf3\Context\Request;
use Hf3\Throwable\Util\HyperfLogger;

abstract class BaseCrontab
{
    /**
     * 定时任务统一入口
     * @return void
     */
    public function execute(): void
    {
        // #[Crontab(callback: 'execute')] 调到这里, 包裹开始/耗时日志与异常兜底,
        // 子类只实现 handle() 纯业务, 抛错不污染调度器
        $task = static::class;
        Request::start($task);
        $traceId = Request::traceId();
        $logger = HyperfLogger::get();
        $startedAt = microtime(true);

        try {
            $this->handle();
        } catch (\Throwable $e) {
            $reason = $e->getMessage();
            $logger?->error("[crontab][{$traceId}] {$task} 异常: {$reason}");
        }

        $endedAt = microtime(true);
        $elapsedMs = (int) round(($endedAt - $startedAt) * 1000);
        $logger?->info("[crontab][{$traceId}] {$task} 执行完成 (耗时 {$elapsedMs}ms)");
    }

    /**
     * 定时任务业务逻辑 —— 子类实现
     * @return void
     */
    abstract protected function handle(): void;
}

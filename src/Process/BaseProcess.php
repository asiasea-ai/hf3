<?php

declare(strict_types=1);

namespace Hf3\Process;

use Hf3\Context\Request;
use Hf3\Throwable\Util\HyperfLogger;
use Hyperf\Process\AbstractProcess;

abstract class BaseProcess extends AbstractProcess
{
    /**
     * 进程循环间隔(秒) —— 子类可覆盖
     * @var int
     */
    protected int $loopIntervalSeconds = 60;

    /**
     * 进程常驻主循环 —— 逐周期调 tick();单周期抛错只记日志不退出,sleep 后继续.
     * @return void
     */
    public function handle(): void
    {
        $proc = static::class;
        $logger = HyperfLogger::get();
        $logger?->info("[process] {$proc} 启动");

        while (true) {
            // 每个周期 seed 一个新 traceId, 让单次 tick 的所有日志可独立串联
            Request::start($proc);
            $traceId = Request::traceId();
            try {
                $this->tick();
            } catch (\Throwable $e) {
                $reason = $e->getMessage();
                $logger?->error("[process][{$traceId}] {$proc} 周期异常: {$reason}");
            }
            sleep($this->loopIntervalSeconds);
        }
    }

    /**
     * 单周期业务逻辑 —— 子类实现
     * @return void
     */
    abstract protected function tick(): void;
}

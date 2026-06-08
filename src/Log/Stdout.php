<?php

declare(strict_types=1);

namespace Hf3\Log;

use Hyperf\Framework\Logger\StdoutLogger;

/**
 * StdoutLogger 噪音过滤 —— 吞掉每个 worker 启动刷屏的 "Worker#N started." 行(启动 banner 的 Workers 卡片已汇总).
 */
class Stdout extends StdoutLogger
{
    public function log($level, $message, array $context = []): void
    {
        $text = (string) $message;
        $isWorkerStarted = (str_starts_with($text, 'Worker#') || str_starts_with($text, 'TaskWorker#'))
            && str_ends_with($text, ' started.');
        if ($isWorkerStarted) {
            return;
        }

        parent::log($level, $message, $context);
    }
}

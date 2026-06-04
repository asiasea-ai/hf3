<?php

declare(strict_types=1);

namespace Hf3\Handler;

use Monolog\Handler\StreamHandler;
use Monolog\LogRecord;

/**
 * 继承 StreamHandler,过滤 Hyperf EventDispatcher 的 "Event X handled by Y listener." debug 噪音.
 */
class NoiseFilterHandler extends StreamHandler
{
    public function handle(LogRecord $record): bool
    {
        if (str_starts_with($record->message, 'Event ') && str_ends_with($record->message, ' listener.')) {
            return true;
        }

        return parent::handle($record);
    }
}

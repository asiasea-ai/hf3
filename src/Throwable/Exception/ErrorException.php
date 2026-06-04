<?php

declare(strict_types=1);

namespace Hf3\Throwable\Exception;

use Hf3\Throwable\AbstractException;
use Hf3\Throwable\Enum\Level;

final class ErrorException extends AbstractException
{
    /**
     * 获取日志级别
     * @return Level
     */
    public function level(): Level
    {
        return Level::Error;
    }
}

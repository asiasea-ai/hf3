<?php

declare(strict_types=1);

namespace Hf3\Throwable\Enum;

enum Level: string
{
    case Info    = 'info';
    case Notice  = 'notice';
    case Warning = 'warning';
    case Error   = 'error';
}

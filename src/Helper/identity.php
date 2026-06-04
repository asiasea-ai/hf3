<?php

declare(strict_types=1);

use Hf3\Context\Request;

function accountId(): int
{
    return Request::getAccountId();
}

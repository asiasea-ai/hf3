<?php

declare(strict_types=1);

use Hf3\Context\Request;

/**
 * 取当前请求上下文的登录账号 ID
 * @return int 账号 ID, 未登录时由 Request::getAccountId() 决定默认值
 */
function accountId(): int
{
    return Request::getAccountId();
}

function companyId(): int
{
    return Request::getCompanyId();
}


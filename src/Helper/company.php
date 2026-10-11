<?php

use Hf3\Context\Request;

/**
 * 获取主体 ID
 */
function companyId(): int
{
    return Request::getCompanyId();
}

/**
 * 获取主体 code
 */
function companyCode(): string
{
    return Request::getCompanyCode();
}
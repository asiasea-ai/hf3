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

/**
 * 获取可见主体 id 列表(多主体)
 */
function companyIds(): array
{
    return Request::getCompanyIds();
}

/**
 * 主体隔离注入值 —— 按 etc('auto.company.multi') 决定单/多:
 *   auto.company.multi=true → companyIds()(数组,find 走 WHERE company_id IN (...))
 *   否则                     → companyId() (单值,WHERE company_id = ?)
 */
function companyValue(): int|array
{
    return (bool) etc('auto.company.multi') ? companyIds() : companyId();
}

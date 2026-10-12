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
 * 获取可见主体 code 列表(多主体;主体 id 各业务系统不是一套时用它)
 */
function companyCodes(): array
{
    return Request::getCompanyCodes();
}

/**
 * 主体隔离的单值 —— 跟着 etc('auto.company.field') 配的隔离列走:
 *   列是 company_code → companyCode()(字符串)
 *   否则               → companyId()  (整数)
 * save / update / delete 写入或限定隔离列时用它
 */
function companyKey(): int|string
{
    $field = (string) (etc('auto.company.field') ?? '');

    return $field === 'company_code' ? companyCode() : companyId();
}

/**
 * 主体隔离注入值(读) —— 按 etc('auto.company.multi') 决定单/多,值的类型跟着隔离列走:
 *   multi=true  → 列是 company_code 用 companyCodes(),否则 companyIds()(数组,find 走 WHERE 列 IN (...))
 *   multi=false → companyKey()(单值,WHERE 列 = ?)
 */
function companyValue(): int|string|array
{
    $multi = (bool) etc('auto.company.multi');
    if (!$multi) {
        return companyKey();
    }
    $field = (string) (etc('auto.company.field') ?? '');

    return $field === 'company_code' ? companyCodes() : companyIds();
}

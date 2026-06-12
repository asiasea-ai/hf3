<?php

declare(strict_types=1);

namespace Hf3\Dao\Util;

use Hf3\Model\Util\Company;
use Hf3\Model\Util\Field;

/**
 * 强制等值条件推导 —— 由 Model FQCN 推导软删 + 租户隔离条件,受管表必带.
 */
final class Forced
{
    /**
     * 推导本次查询必带的强制等值条件
     *
     * - 软删列 delete_flg = 0(只查未删行)
     * - 公司列 company_id = 当前公司(SaaS 隔离,受管表 fail-closed,无上下文抛错)
     * 表 schema 不可读(如动态分表无基表)时各列均不命中,自动退化为空 map.
     * @param class-string $modelClass
     * @return array<string, mixed> [列名 => 值] map
     */
    public static function conditions(string $modelClass): array
    {
        $columns = Field::select($modelClass);
        $forced = [];

        $deleteField = Field::deleteFlg($columns);
        if ($deleteField !== '') {
            $forced[$deleteField] = 0;
        }

        [$companyColumn, $companyId] = Company::filter($columns);
        if ($companyColumn !== '') {
            $forced[$companyColumn] = $companyId;
        }

        return $forced;
    }
}

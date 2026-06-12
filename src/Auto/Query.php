<?php

declare(strict_types=1);

namespace Hf3\Auto;

use Hf3\Model\Util\Field;

/**
 * 强制等值条件推导 —— 由 Model FQCN 推导软删 + 租户隔离条件,受管表必带.
 */
class Query
{
    /**
     * 推导本次查询必带的强制等值条件
     *
     * - 软删列 delete_flg = 0(只查未删行)
     * - 公司列 company_id = 当前公司(SaaS 隔离,开关开启且表实际含该列才注入)
     * 表 schema 不可读(如动态分表无基表)时各列均不命中,自动退化为空 map.
     * @param class-string $model Model FQCN
     * @return array<string, mixed> [列名 => 值] map
     */
    public static function all(string $model): array
    {
        $auto = [];

        /** 如果存在逻辑删除赋上 */
        $deleteField = Field::deleteFlg($model);
        if (!superEmpty($deleteField)) {
            $auto[$deleteField] = 0;
        }

        /** 如果存在租户列且实际存在于本表,赋上当前公司 */
        $companyField = Field::companyId($model);
        if (!superEmpty($companyField)) {
            $auto[$companyField] = companyId();
        }

        return $auto;
    }
}
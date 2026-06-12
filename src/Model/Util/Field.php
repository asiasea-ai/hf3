<?php

declare(strict_types=1);

namespace Hf3\Model\Util;

class Field
{
    /**
     * 获取软删标记列 —— 由 Model FQCN 取表列名 list,表实际含 etc 配置的软删列时返列名,否则返空串
     *
     * 列名走 $model::fieldList() 取得,连接跟随 Model::CONNECTION;
     * 未配置软删列 / 表不含该列 / schema 不可读(表不存在 / 动态分表无基表),均返空串.
     * @param class-string $model Model FQCN
     * @return string
     */
    public static function deleteFlg(string $model): string
    {
        /** 配置的软删列名 */
        $field = (string) (etc('auto.delete.delete_flg') ?? '');
        if (superEmpty($field)) {
            return '';
        }

        /** 表实际含该列才走软删 */
        $columns = $model::fieldList();
        if (!in_array($field, $columns, true)) {
            return '';
        }

        return $field;
    }

    /**
     * 按本表列名白名单过滤数组 —— 由 Model FQCN 取列名 list,剔除非表列的键
     * @param class-string $model Model FQCN
     * @param array $input
     * @return array
     */
    public static function clean(string $model, array $input): array
    {
        $allowed = $model::fieldList();
        return array_intersect_key($input, array_flip($allowed));
    }

    /**
     * 获取公司隔离列 —— 由 Model FQCN 取表列名 list,开关开启且表实际含 etc 配置的公司列时返列名,否则返空串
     *
     * 列名走 $model::fieldList() 取得,连接跟随 Model::CONNECTION;
     * 开关关闭 / 未配置公司列 / 表不含该列(非受管表) / schema 不可读,均返空串.
     * @param class-string $model Model FQCN
     * @return string
     */
    public static function companyId(string $model): string
    {
        /** 总开关 */
        $enable = (bool) etc('auto.company.enable', true);
        if (superEmpty($enable)) {
            return '';
        }

        /** 配置的公司列名 */
        $field = (string) (etc('auto.company.field') ?? '');
        if (superEmpty($field)) {
            return '';
        }

        /** 表实际含该列才算受管表 */
        $columns = $model::fieldList();
        if (!in_array($field, $columns, true)) {
            return '';
        }

        return $field;
    }
}

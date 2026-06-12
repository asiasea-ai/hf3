<?php

declare(strict_types=1);

namespace Hf3\Safe;

use Hf3\Code\Code;
use Hf3\Db\Util\Sql;
use Hf3\Model\Util\Field;
use Hf3\Throwable\Exception\ErrorException;

/**
 * SQL 公司隔离守卫 —— 受管表 SQL 必须按公司列过滤,纯检测、不改写,漏带即抛.
 */
final class Company
{
    /**
     * 检测 SQL 是否对本 Model 的受管表带了公司隔离列
     *
     * 受管判定走 Field::companyId($model)(总开关 + etc 配置列 + 表实际含该列);
     *   - 单表:SQL 出现公司列(限定或不限定)即可;
     *   - 多表(join):本 Model 的表必须出现限定写法 别名.公司列(或 表名.公司列).
     * 开关关闭 / 非受管表 / SQL 未涉及本 Model 的表(如动态分表实表名)→ 放行;
     * join 的其它表不在本检测范围,由各自 Dao 的 SQL 自行负责.
     * @param string $sql 待入库 SQL
     * @param class-string $model Model FQCN
     * @return void
     */
    public static function check(string $sql, string $model): void
    {
        /** 本 Model 的公司隔离列 —— 开关关闭 / 非受管表直接放行 */
        $column = Field::companyId($model);
        if ($column === '') {
            return;
        }

        /** SQL 未涉及本 Model 的表(动态分表实表名等)放行 */
        $tables = Sql::tables($sql);
        $table = (string) constant("{$model}::NAME");
        if (!array_key_exists($table, $tables)) {
            return;
        }

        /** 单表裸列名即可,多表必须 别名.公司列 限定写法 */
        $alias = (string) $tables[$table];
        $reference = $alias !== '' ? $alias : $table;
        $multiTable = count($tables) > 1;
        $pattern = $multiTable
            ? '/\b' . preg_quote($reference, '/') . '\s*\.\s*' . preg_quote($column, '/') . '\b/i'
            : '/\b' . preg_quote($column, '/') . '\b/i';
        if (preg_match($pattern, $sql) === 1) {
            return;
        }

        throw new ErrorException(
            code: Code::MODEL_COMPANY_SQL_UNGUARDED,
            message: isProduction()
                ? '请求被拒绝'
                : "受管表 `{$table}` 未按公司列过滤(需 {$reference}.{$column})—— 数据隔离风险" . PHP_EOL . "SQL: {$sql}",
            category: 'sql/company',
        );
    }
}

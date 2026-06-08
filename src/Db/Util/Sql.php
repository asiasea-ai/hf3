<?php

declare(strict_types=1);

namespace Hf3\Db\Util;

/**
 * 从 SQL 抽取涉及的表名 + 别名 —— 供主体隔离逐表判定.
 *
 * 覆盖 SELECT / INSERT / REPLACE / UPDATE / DELETE 的 FROM / JOIN / 逗号联表 / 子查询 / UNION / schema 限定名;
 * 深层嵌套子查询 / CTE / 派生表属 best-effort(纯正则无法完全解析 SQL),解析不到的表不参与判定.
 */
final class Sql
{
    /** FROM/UPDATE 子句捕获的终止关键字(其后不再是表) */
    private const string STOP = '\bWHERE\b|\bSET\b|\bVALUES\b|\bGROUP\b|\bORDER\b|\bLIMIT\b|\bHAVING\b|\bUNION\b'
        . '|\bON\b|\bUSING\b|\bLEFT\b|\bRIGHT\b|\bINNER\b|\bOUTER\b|\bCROSS\b|\bJOIN\b|\(|\)|;|$';

    /** 别名位置上不能出现的 SQL 关键字(否则会把 JOIN/ON 等当别名吞掉) */
    private const string KW = 'JOIN|LEFT|RIGHT|INNER|OUTER|CROSS|ON|USING|WHERE|GROUP|ORDER|LIMIT|HAVING|UNION|SET|VALUES|SELECT';

    /** 单个表引用:可选 `schema`. 前缀 + 表名 + 可选 [AS] 别名(别名不得为 SQL 关键字) */
    private const string REF = '(?:`?\w+`?\.)?`?\w+`?(?:\s+(?:AS\s+)?(?!(?:' . self::KW . ')\b)`?\w+`?)?';

    /**
     * 解析 SQL 涉及的表 —— 返回 [表名 => 别名](无别名为空串)
     * @param string $sql
     * @return array<string, string>
     */
    public static function tables(string $sql): array
    {
        $tables = [];

        /** A) FROM / UPDATE 子句:取到下一个子句关键字为止,按逗号拆(支持 FROM a, b / UPDATE a, b 旧式多表) */
        if (preg_match_all('/\b(?:FROM|UPDATE)\s+(.+?)(?=' . Sql::STOP . ')/is', $sql, $clauses, PREG_SET_ORDER) > 0) {
            foreach ($clauses as $clause) {
                foreach (explode(',', $clause[1]) as $reference) {
                    [$table, $alias] = Sql::parseRef($reference);
                    if ($table !== '') {
                        $tables[$table] = $alias;
                    }
                }
            }
        }

        /** B) 逐关键字抓单表 —— JOIN / INTO(INSERT|REPLACE),并兜底再扫 FROM/UPDATE 抓子查询/UNION 里的表 */
        if (preg_match_all('/\b(?:JOIN|INTO|FROM|UPDATE)\s+(' . Sql::REF . ')/i', $sql, $refs, PREG_SET_ORDER) > 0) {
            foreach ($refs as $ref) {
                [$table, $alias] = Sql::parseRef($ref[1]);
                if ($table !== '' && !isset($tables[$table])) {
                    $tables[$table] = $alias;
                }
            }
        }

        return $tables;
    }

    /**
     * 解析单个表引用 "[schema.]table [AS] alias" → [表名, 别名];子查询/空 → ['', '']
     * @param string $reference
     * @return array{0: string, 1: string}
     */
    public static function parseRef(string $reference): array
    {
        $reference = trim(str_replace('`', '', $reference));
        if ($reference === '' || $reference[0] === '(') {
            return ['', ''];
        }
        if (preg_match('/^([\w.]+)(?:\s+(?:as\s+)?(\w+))?/i', $reference, $matches) !== 1) {
            return ['', ''];
        }

        /** schema.table → 取最后一段表名(库名前缀丢弃,Schema 按当前连接内省) */
        $table = $matches[1];
        if (str_contains($table, '.')) {
            $segments = explode('.', $table);
            $table = (string) end($segments);
        }

        $alias = $matches[2] ?? '';
        $keywords = ['ON', 'USING', 'WHERE', 'SET', 'VALUES', 'AS', 'GROUP', 'ORDER', 'LIMIT', 'HAVING', 'UNION'];
        if ($alias !== '' && in_array(strtoupper($alias), $keywords, true)) {
            $alias = '';
        }

        return [$table, $alias];
    }
}

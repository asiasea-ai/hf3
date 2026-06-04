<?php

declare(strict_types=1);

namespace Hf3\Model\Util;

use Hf3\Code\Code;
use Hf3\Throwable\Exception\ErrorException;

final class Safe
{
    /**
     * 检查 SQL 参数绑定
     * @param string $sql
     * @return void
     */
    public static function bind(string $sql): void
    {

        $stripped = (string) preg_replace(
            ["/'(?:[^'\\\\]|\\\\.)*'/", '/"(?:[^"\\\\]|\\\\.)*"/', '/`[^`]*`/'],
            ['V', 'V', 'B'],
            $sql,
        );

        $stripped = (string) preg_replace('/[A-Za-z_]\w*\.B/', 'B', $stripped);

        if (preg_match('/(?<![<>!=:])=(?!=)\s*([^?B\s])/', $stripped, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return;
        }

        $context = substr($sql, max(0, (int) $m[0][1] - 20), 50);
        throw new ErrorException(
            code: Code::SQL_PARAM_NOT_BOUND,
            message: "SQL 未参数绑定: `=` 右侧应为 `?` 或反引号列名,实际 `{$m[1][0]}`" . PHP_EOL . "上下文: ...{$context}...",
            category: 'sql/bind',
        );
    }

    /**
     * 检查 SQL 注入风险
     * @param string $sql
     * @return void
     */
    public static function inject(string $sql): void
    {

        $patterns = [

            '/(?:--|#)(?:\s|$)/'                            => 'SQL 行注释 (-- / #)',

            '/\/\*.*?\*\//s'                                => 'SQL 块注释 (/* ... */)',

            '/;\s*\S/'                                      => '堆叠查询 (; 后还跟语句)',

            '/\b\d+\s*=\s*\d+\b/'                           => '数字常量重言式 (1=1 / 1=2 类)',

            '/\b(?:OR|AND)\s+(?:TRUE|FALSE)\b/i'            => '布尔常量 (OR TRUE / AND FALSE)',

            '/\bOR\s+[\'"]?\w+[\'"]?\s*=\s*[\'"]?\w+[\'"]?/i'  => '布尔重言式 (OR word=word / OR \'a\'=\'a\')',
            '/\bAND\s+[\'"]?\w+[\'"]?\s*=\s*[\'"]?\w+[\'"]?/i' => '布尔重言式 (AND word=word)',
            '/\bOR\s+\d+\s*=\s*\d+/i'                          => '布尔重言式 (OR 1=1 数字型)',

            '/\bUNION\s+(?:ALL\s+)?SELECT\b/i'              => 'UNION SELECT (典型 union-based 注入)',

            '/\b(?:SLEEP|BENCHMARK|PG_SLEEP)\s*\(/i'        => '时间盲注函数 (SLEEP / BENCHMARK / PG_SLEEP)',
            '/\bWAITFOR\s+DELAY\b/i'                        => '时间盲注 (WAITFOR DELAY)',

            '/\bINFORMATION_SCHEMA\b/i'                     => 'INFORMATION_SCHEMA (枚举表/列结构)',
            '/\bmysql\.\s*(?:user|db|tables_priv)\b/i'      => 'mysql 系统表 (mysql.user / mysql.db)',

            '/\b(?:EXTRACTVALUE|UPDATEXML)\s*\(/i'          => 'error-based 注入 (EXTRACTVALUE / UPDATEXML)',
            '/\bFLOOR\s*\(\s*RAND\s*\(/i'                   => 'error-based 注入 (FLOOR(RAND))',

            '/\b(?:database|version|current_user|session_user|system_user)\s*\(\s*\)/i' => '信息回显函数 (database/version/user)',
            '/\buser\s*\(\s*\)/i'                           => '信息回显函数 user()',
            '/@@(?:version|hostname|datadir|tmpdir|version_compile_os)/i' => 'MySQL 系统变量 (@@version 类)',

            '/\bCONCAT(?:_WS)?\s*\(/i'                      => 'CONCAT() 字符串拼接(注入常用)',

            '/\bLOAD_FILE\s*\(/i'                           => 'LOAD_FILE() 读服务器文件',
            '/\bINTO\s+(?:OUT|DUMP)FILE\b/i'                => 'INTO OUTFILE / DUMPFILE 写服务器文件',

            '/\b(?:DROP|ALTER|TRUNCATE|RENAME)\s+(?:TABLE|DATABASE|INDEX|VIEW)\b/i' => 'DDL 操作 (DROP/ALTER/TRUNCATE TABLE 类)',

            '/\'(\w+)\'\s*=\s*\'\1\'/'                      => '字符串重言式 (\'a\'=\'a\' 类)',

            '/\b0x[0-9a-f]{2,}\b/i'                         => 'hex 编码 (0x...)',
            '/\bCHAR\s*\(\s*\d/i'                           => 'CHAR(N,...) ASCII 编码',
        ];

        $unescaped = stripslashes($sql);

        $normalized = rawurldecode($unescaped);

        foreach ($patterns as $pattern => $msg) {
            if (preg_match($pattern, $normalized) !== 1) {
                continue;
            }

            throw new ErrorException(
                code: Code::SQL_INJECTION_SUSPECTED,
                message: isProduction()
                    ? 'SQL 注入嫌疑'
                    : "SQL 注入嫌疑: {$msg}" . PHP_EOL . "SQL: {$sql}",
                category: 'sql/inject',
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace Hf3\Safe;

use Hf3\Code\Code;
use Hf3\Throwable\Exception\ErrorException;

/**
 * SQL 参数绑定守卫 —— `=` 右侧必须是 `?` 占位符或反引号列名,裸字面量即抛.
 */
final class Bind
{
    /**
     * 检查 SQL 参数绑定
     * @param string $sql
     * @return void
     */
    public static function check(string $sql): void
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
}

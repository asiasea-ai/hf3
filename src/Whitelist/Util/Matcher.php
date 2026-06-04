<?php

declare(strict_types=1);

namespace Hf3\Whitelist\Util;

use Closure;

/**
 * 白名单规则匹配工具 —— 纯静态,供 Whitelist 消费.
 */
final class Matcher
{
    /**
     * 检查HTTP方法是否匹配规则
     * @param string $rule
     * @param string $actual
     * @return bool
     */
    public static function methodHit(string $rule, string $actual): bool
    {
        return $rule === '**' || $rule === $actual;
    }

    /**
     * 含 `*` 的路径预编译为 regex closure;纯字符串返 null(走相等判断,省 regex)
     * @param string $path
     * @return Closure|null
     */
    public static function compilePathMatcher(string $path): ?Closure
    {
        if (!str_contains($path, '*')) {
            return null;
        }
        $regex = '#^' . str_replace('\*', '.*', preg_quote($path, '#')) . '$#';
        return static fn (string $p): bool => preg_match($regex, $p) === 1;
    }
}

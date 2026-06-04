<?php

declare(strict_types=1);

namespace Hf3\Dto\Util;

/**
 * DTO 字段中文名解析 —— reflection 取 promoted property 上方的 doc comment.
 */
final class Label
{
    /** @var array<string, array<string, string|null>> per (class, field) 缓存 */
    private static array $cache = [];

    /**
     * 取字段简短中文名(用于客户端错误提示),无 doc comment 或字段不存在返 null.
     *
     * 只取 PHPDoc 第一段 —— 首个 `(` / `（` / `——` / `:` / `：` 之前的文字.
     * 括号 / 破折号后通常是字段格式 / 内部约定(如 base64 编码规则 / cursor 解码字段名),
     * 这些回灌到客户端报错里会暴露实现细节,被攻击者利用伪造请求.
     * @param string $dtoClass
     * @param string $field
     * @return string|null
     */
    public static function of(string $dtoClass, string $field): ?string
    {
        if (isset(Label::$cache[$dtoClass]) && array_key_exists($field, Label::$cache[$dtoClass])) {
            return Label::$cache[$dtoClass][$field];
        }

        $doc = false;
        try {
            $rc = new \ReflectionClass($dtoClass);
            if ($rc->hasProperty($field)) {
                $doc = $rc->getProperty($field)->getDocComment();
            }
        } catch (\ReflectionException) {
            // 取不到当 null 缓存
        }

        if ($doc === false || $doc === '') {
            Label::$cache[$dtoClass][$field] = null;
            return null;
        }

        $stripped = preg_replace('#^/\*+\s*|\s*\*+/$#u', '', $doc);
        $lines = preg_split('/\R/u', (string) $stripped);
        $clean = array_map(static fn (string $l): string => trim($l, " \t*"), $lines);
        $nonEmpty = array_filter($clean, static fn (string $l): bool => $l !== '' && !str_starts_with($l, '@'));
        $merged = trim(implode(' ', $nonEmpty));

        $brief = preg_split('/[(（:：]|\s*——\s*|\s+@\w/u', $merged, 2)[0] ?? $merged;
        $brief = trim($brief);

        $label = $brief === '' ? null : $brief;
        Label::$cache[$dtoClass][$field] = $label;
        return $label;
    }
}

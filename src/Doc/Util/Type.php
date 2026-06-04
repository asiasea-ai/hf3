<?php

declare(strict_types=1);

namespace Hf3\Doc\Util;

use ReflectionNamedType;
use ReflectionType;

/**
 * PHP 类型 → OpenAPI 类型映射.
 */
final class Type
{
    /**
     * 把 PHP 标量类型名翻成 OpenAPI 类型字符串
     * @param string $php  string / int / float / bool / array / 其它
     * @return string|null  null 表示不是标量(可能是 VO 类),caller 自己处理
     */
    public static function scalar(string $php): ?string
    {
        return match ($php) {
            'string' => 'string',
            'int'    => 'integer',
            'float'  => 'number',
            'bool'   => 'boolean',
            'array'  => 'array',
            default  => null,
        };
    }

    /**
     * 给 ReflectionType 拼一份 OpenAPI schema 片段(只处理标量;非标量返 [])
     * @param ReflectionType|null $rt
     * @return array<string, mixed>
     */
    public static function fromReflection(?ReflectionType $rt): array
    {
        if (!$rt instanceof ReflectionNamedType) {
            return [];
        }
        $name = $rt->getName();
        $openApi = Type::scalar($name);
        $out = [];
        if ($openApi !== null) {
            $out['type'] = $openApi;
        }
        if ($rt->allowsNull()) {
            $out['nullable'] = true;
        }
        return $out;
    }

    /**
     * ReflectionNamedType 是不是命名的类(VO / DTO 等),返回 FQCN 或 null
     * @param ReflectionType|null $rt
     * @return string|null
     */
    public static function classFqcn(?ReflectionType $rt): ?string
    {
        if (!$rt instanceof ReflectionNamedType || $rt->isBuiltin()) {
            return null;
        }
        return $rt->getName();
    }
}

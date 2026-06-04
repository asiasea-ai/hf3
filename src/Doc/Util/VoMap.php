<?php

declare(strict_types=1);

namespace Hf3\Doc\Util;

use Hf3\Dto\Util\Label;
use ReflectionClass;

/**
 * VO ctor → OpenAPI components.schemas 片段映射(递归处理嵌套 VO).
 */
final class VoMap
{
    /**
     * 拿到 VO 的 OpenAPI schema(单值或带 items 数组),会顺带把依赖的子 VO 也注入 schemas pool.
     * @param class-string $voClass
     * @param array<string, array<string, mixed>> &$pool 递归注入的 schemas 池(class shortName → schema)
     * @return array<string, mixed>  $ref 引用片段,可直接挂到 properties 下
     */
    public static function ref(string $voClass, array &$pool): array
    {
        $shortName = VoMap::shortName($voClass);
        if (!isset($pool[$shortName])) {
            // 先占位防止循环引用
            $pool[$shortName] = ['type' => 'object'];
            $pool[$shortName] = VoMap::build($voClass, $pool);
        }
        return ['$ref' => '#/components/schemas/' . $shortName];
    }

    /**
     * 构造 VO 的完整 object schema
     * @param class-string $voClass
     * @param array<string, array<string, mixed>> &$pool
     * @return array<string, mixed>
     */
    private static function build(string $voClass, array &$pool): array
    {
        $rc = new ReflectionClass($voClass);
        $ctor = $rc->getConstructor();
        if ($ctor === null) {
            return ['type' => 'object'];
        }

        $properties = [];
        foreach ($ctor->getParameters() as $p) {
            $name = $p->getName();
            $rt = $p->getType();

            $fqcn = Type::classFqcn($rt);
            if ($fqcn !== null) {
                /** 嵌套 VO 字段 → 递归 ref */
                $prop = VoMap::ref($fqcn, $pool);
            } else {
                $prop = Type::fromReflection($rt);
                /** array 字段 —— 看 PHPDoc @var XxxVo[] 或字段名 list */
                if (($prop['type'] ?? null) === 'array') {
                    $itemClass = VoMap::detectArrayItem($voClass, $name, $p);
                    if ($itemClass !== null) {
                        $prop['items'] = VoMap::ref($itemClass, $pool);
                    }
                }
            }

            $description = Label::of($voClass, $name);
            if ($description !== null) {
                $prop['description'] = $description;
            }
            $properties[$name] = $prop;
        }

        return ['type' => 'object', 'properties' => $properties];
    }

    /**
     * 识别数组字段的元素类型 —— PHPDoc `@var XxxVo[]` 或 `array<XxxVo>` 或字段名为 `list`
     * @param string $voClass
     * @param string $field
     * @param \ReflectionParameter $p
     * @return class-string|null
     */
    private static function detectArrayItem(string $voClass, string $field, \ReflectionParameter $p): ?string
    {
        $rc = new ReflectionClass($voClass);
        if (!$rc->hasProperty($field)) {
            return null;
        }
        $doc = $rc->getProperty($field)->getDocComment();
        if ($doc === false) {
            return null;
        }

        /** 匹配 `@var XxxVo[]` */
        if (preg_match('/@var\s+([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)\[\]/u', $doc, $m)) {
            $class = VoMap::resolveClass($voClass, $m[1]);
            return $class !== null && class_exists($class) ? $class : null;
        }
        /** 匹配 `array<XxxVo>` 或 `list<XxxVo>` */
        if (preg_match('/(?:array|list)<\s*([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)\s*>/u', $doc, $m)) {
            $class = VoMap::resolveClass($voClass, $m[1]);
            return $class !== null && class_exists($class) ? $class : null;
        }
        return null;
    }

    /**
     * 把 PHPDoc 里写的相对类名解析到完整 FQCN —— 走当前 VO 文件的 use 表
     * @param string $voClass
     * @param string $rawName
     * @return string|null
     */
    private static function resolveClass(string $voClass, string $rawName): ?string
    {
        $trimmed = ltrim($rawName, '\\');
        if (class_exists($trimmed)) {
            return $trimmed;
        }
        $rc = new ReflectionClass($voClass);
        $ns = $rc->getNamespaceName();
        $sameNs = $ns . '\\' . $trimmed;
        if (class_exists($sameNs)) {
            return $sameNs;
        }
        /** use 表解析未实现 —— 让 caller 写 FQCN 形式或同 namespace 形式 */
        return null;
    }

    /**
     * FQCN → 短名(去 namespace),冲突时加上 namespace 倒数第二段做前缀
     * @param string $fqcn
     * @return string
     */
    public static function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        $last = (string) array_pop($parts);
        /** Account / List 这种容易撞,加上倒数第二段 namespace 段 */
        $prev = (string) (array_pop($parts) ?? '');
        return $prev !== '' ? $prev . $last : $last;
    }
}

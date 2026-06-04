<?php

declare(strict_types=1);

namespace Hf3\Spl\Util;

/**
 * 构造器参数元数据 —— reflection 解析 [name => {type, hasDefault}],per-process 缓存.
 */
final class CtorMeta
{
    /** @var array<string, array<string, array{type: string, hasDefault: bool}>|null> */
    private static array $cache = [];

    /**
     * 取构造器参数元数据,无 ctor 返 null
     * @param string $class
     * @return array<string, array{type: string, hasDefault: bool}>|null
     */
    public static function of(string $class): ?array
    {
        if (array_key_exists($class, CtorMeta::$cache)) {
            return CtorMeta::$cache[$class];
        }

        $ctor = new \ReflectionClass($class)->getConstructor();
        if ($ctor === null) {
            CtorMeta::$cache[$class] = null;
            return null;
        }

        $meta = [];
        foreach ($ctor->getParameters() as $p) {
            $type = $p->getType();
            $typeName = ($type instanceof \ReflectionNamedType && $type->isBuiltin())
                ? $type->getName()
                : 'mixed';
            $meta[$p->getName()] = [
                'type' => $typeName,
                'hasDefault' => $p->isDefaultValueAvailable(),
            ];
        }
        CtorMeta::$cache[$class] = $meta;
        return $meta;
    }
}

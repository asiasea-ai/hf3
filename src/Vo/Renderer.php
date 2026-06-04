<?php

declare(strict_types=1);

namespace Hf3\Vo;

final class Renderer
{
    /**
     * VO 转为数组
     * @param object $vo
     * @return array
     */
    public static function toArray(object $vo): array
    {
        $rc = new \ReflectionClass($vo);
        $ctor = $rc->getConstructor();
        if ($ctor === null) {
            return [];
        }

        $arr = [];
        foreach ($ctor->getParameters() as $p) {
            $name = $p->getName();
            $value = $vo->{$name} ?? null;
            $arr[$name] = Renderer::convertValue($value);
        }
        return $arr;
    }

    /**
     * 递归转换值
     * @param mixed $value
     * @return mixed
     */
    private static function convertValue(mixed $value): mixed
    {
        if (is_object($value) && Renderer::isVo($value::class)) {
            return Renderer::toArray($value);
        }
        if (is_array($value)) {
            return array_map(Renderer::convertValue(...), $value);
        }
        return $value;
    }

    /**
     * 判断是否为 VO 类
     * @param string $className
     * @return bool
     */
    private static function isVo(string $className): bool
    {
        return str_contains($className, '\\Vo\\');
    }
}

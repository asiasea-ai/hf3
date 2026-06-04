<?php

declare(strict_types=1);

namespace Hf3\Service\Util;

use Hf3\Throwable\Exception\WarnException;

final class Entity
{
    /**
     * 根据 Service 类获取对应 Code 类
     * @param string $serviceClass
     * @return string
     */
    public static function code(string $serviceClass): string
    {

        return str_replace('\\Service\\', '\\Code\\', $serviceClass);
    }

    /**
     * 确保查询结果不为空
     * @param string $serviceClass
     * @param array|null $row
     * @param string|int $key
     * @return array
     */
    public static function ensureFound(string $serviceClass, ?array $row, string|int $key): array
    {
        if ($row !== null) {
            return $row;
        }

        $code = Entity::code($serviceClass);
        throw new WarnException(
            code: $code::notFound(),
            message: "{$code::entityName()} {$key} 不存在",
        );
    }
}

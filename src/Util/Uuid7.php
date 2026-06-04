<?php

declare(strict_types=1);

namespace Hf3\Util;

use Symfony\Component\Uid\Uuid;

/**
 * 有序 UUID 生成器 —— 基于 UUIDv7,36 位 RFC 4122 字符串,时间有序且本地生成无需协调.
 */
final class Uuid7
{
    /**
     * 生成一个有序 UUID(v7) —— 前 48 位是毫秒时间戳,字典序可排,全局唯一.
     * @return string
     */
    public static function gen(): string
    {
        $uuid = Uuid::v7();
        return $uuid->toRfc4122();
    }
}

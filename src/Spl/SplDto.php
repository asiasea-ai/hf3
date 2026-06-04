<?php

declare(strict_types=1);

namespace Hf3\Spl;

readonly class SplDto implements \JsonSerializable
{
    /**
     * 转为数组 —— 默认过滤掉 null 字段(PATCH 语义:省略字段不参与 UPDATE);
     * 空字符串 "" 保留(显式传 "" 视为业务意图,要写进 DB).
     *
     * 想拿到原始全字段(含 null)用 toArray(false).
     * @param bool $cleanNull
     * @return array
     */
    public function toArray(bool $cleanNull = true): array
    {
        $array = get_object_vars($this);
        return $cleanNull
            ? array_filter($array, static fn (mixed $v): bool => $v !== null)
            : $array;
    }

    /**
     * JSON 序列化
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

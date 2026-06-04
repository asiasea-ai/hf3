<?php

declare(strict_types=1);

namespace Hf3\Spl;

class SplIdentity implements \JsonSerializable
{
    /**
     * 转为数组 —— 默认过滤掉 null 字段;空字符串 "" 保留.
     * 跟 SplDto::toArray 行为一致,见那里的注释.
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

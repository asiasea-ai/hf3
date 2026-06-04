<?php

declare(strict_types=1);

namespace Hf3\Model\Util;

final class Adapter
{
    /**
     * 类型转换数据行
     * @param array $fields
     * @param array $row
     * @return array
     */
    public static function castRow(array $fields, array $row): array
    {
        foreach ($row as $col => $val) {
            if ($val === null) {
                continue;
            }
            $row[$col] = match ($fields[$col]['type'] ?? 'string') {
                'int'   => (int) $val,
                'float' => (float) $val,
                'bool'  => (bool) (int) $val,
                default => $val,
            };
        }
        return $row;
    }
}

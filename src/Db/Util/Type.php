<?php

declare(strict_types=1);

namespace Hf3\Db\Util;

final class Type
{
    public static function scalar(string $dataType): string
    {
        return match (true) {
            in_array($dataType, ['datetime', 'timestamp'], true) => 'datetime',
            in_array($dataType, ['date', 'time', 'json'], true) => $dataType,
            in_array($dataType, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'], true) => 'int',
            in_array($dataType, ['decimal', 'numeric', 'float', 'double', 'real'], true) => 'float',
            default => 'string',
        };
    }
}

<?php

declare(strict_types=1);

namespace Hf3\Db\Util;

final class Type
{
    /**
     * 把数据库列类型归一化为标量类型
     * @param string $dataType 数据库原始列类型, 如 datetime / bigint / decimal
     * @return string 归一化标量: datetime / date / time / json / int / float / string
     */
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

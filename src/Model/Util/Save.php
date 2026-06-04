<?php

declare(strict_types=1);

namespace Hf3\Model\Util;

use Hf3\Code\Code;
use Hf3\Throwable\Exception\WarnException;

class Save
{
    /**
     * 批量生成 INSERT 语句
     * @param string $tableName
     * @param array $data
     * @return array
     */
    public static function all(string $tableName, array $data): array
    {
        if (superEmpty($data)) {
            throw new WarnException(
                code: Code::TABLE_NOT_FOUND,
                message: 'Save::all() 入参 $data 不能为空',
                category: 'model/save',
            );
        }

        $sql = "INSERT INTO `$tableName` ";
        $params = [];

        $firstItemKeys = array_keys($data[0]);
        $columns = implode('`, `', $firstItemKeys);
        $sql .= "(`$columns`) VALUES ";

        foreach ($data as $row) {
            $placeholders = array_fill(0, count($row), '?');
            $sql .= '(' . implode(', ', $placeholders) . '),';
            $params = array_merge($params, array_values($row));
        }

        $sql = rtrim($sql, ',') . ';';

        return ['sql' => $sql, 'bind' => $params];
    }
}

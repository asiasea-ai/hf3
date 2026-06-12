<?php

declare(strict_types=1);

namespace Hf3\Model\Util;

use Hf3\Code\Code;
use Hf3\Db\Schema;
use Hf3\Throwable\Exception\ErrorException;
use Hf3\Throwable\Exception\WarnException;

class Field
{
    /**
     * 获取软删标记列 —— 在"列名 list"中匹配 etc 配置的候选,0 个返空、1 个返列名、>1 抛错(schema 错配)
     *
     * 入参语义跟 Field::select() / Field::clean() 对齐 —— 都是 **list of 列名**,不是 FIELDS map.
     * 若调用方手里是 FIELDS map,先 array_keys() 再传进来.
     * @param list<string> $columns 列名 list(常通过 Field::select(static::class) 取得)
     * @return string
     */
    public static function deleteFlg(array $columns): string
    {
        $candidates = etc('auto.delete.delete_flg');
        if (!is_array($candidates)) {
            return '';
        }

        $matched = array_intersect($candidates, $columns);

        if (count($matched) > 1) {
            throw new WarnException(
                code: Code::MODEL_DELETE_FLG_AMBIGUOUS,
                message: 'FIELDS 同时命中多个软删候选列 [' . implode(', ', $matched) . '] —— schema 错配,请只保留一个',
                category: 'audit/delete-flg',
            );
        }

        return (string) current($matched);
    }

    /**
     * 获取可查询字段白名单 —— 走 Schema::inspect 取 Model::NAME 对应表的列名 list,连接跟随 Model::CONNECTION
     * @param class-string $modelClass
     * @return list<string>
     */
    public static function select(string $modelClass): array
    {
        $table = (string) constant("{$modelClass}::NAME");
        $connection = (string) constant("{$modelClass}::CONNECTION");
        $fields = Schema::inspect($table, $connection);
        return array_keys($fields);
    }

    /**
     * 按白名单过滤数组
     * @param array $allowed
     * @param array $input
     * @return array
     */
    public static function clean(array $allowed, array $input): array
    {
        return array_intersect_key($input, array_flip($allowed));
    }

}

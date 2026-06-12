<?php

declare(strict_types=1);

namespace Hf3\Auto;

use Hf3\Model\Util\Auto;
use Hf3\Model\Util\Field;

/**
 * 软删 patch 推导 —— 由 Model FQCN 推导软删更新数据,非软删表返空 map.
 */
class Delete
{
    /**
     * 推导本次删除应执行的软删 patch
     *
     * - 软删列 = 1(标记已删)
     * - delete_time / delete_account_id 审计字段(etc 候选与表列求交集,表含才注入)
     * 表无软删列(含 schema 不可读)返空 map,调用方据此走物理删除.
     * @param class-string $model Model FQCN
     * @return array<string, mixed> [列名 => 值] map
     */
    public static function all(string $model): array
    {
        /** 软删列 —— 不存在即非软删表 */
        $deleteField = Field::deleteFlg($model);
        if (superEmpty($deleteField)) {
            return [];
        }

        /** 软删标记 + 审计字段 */
        $patch = [$deleteField => 1];
        $patch += Auto::time($model, 'delete_time');
        $patch += Auto::accountId($model, 'delete');

        return $patch;
    }
}

<?php

declare(strict_types=1);

namespace Hf3\Dao\Util;

use Hf3\Code\Code;
use Hf3\Dao\Base\Listing;
use Hf3\Model\BaseModel;
use Hf3\Model\Util\Field;
use Hf3\Throwable\Exception\ErrorException;

final class Inspect
{
    /**
     * 由 Model FQCN 推导其对应的 Listing 拼装工具类 FQCN
     *
     * @param BaseModel $model
     * @return class-string<Listing>
     */
    public static function listingUtilClass(BaseModel $model): string
    {
        return str_replace('\\Model\\', '\\Util\\', $model::class) . '\\Dao\\Listing';
    }

    /**
     * 拼接 SELECT 列表 —— 走 Schema 取列名,过滤 delete_time / 软删标记列,反引号包裹拼接
     *
     * Schema 不可读(表不存在 / 无 SELECT 权限)时 $allCols=[],
     * 直接抛 TABLE_NOT_FOUND 比让 SQL 拼成 `SELECT `` FROM xxx` 在 DB 层报错更清晰.
     * @param BaseModel $model
     * @return string
     * @throws ErrorException Schema 不可读或表无列时
     */
    public static function selectField(BaseModel $model): string
    {
        $allCols = Field::select($model::class);
        if ($allCols === []) {
            $table = (string) constant($model::class . '::NAME');
            throw new ErrorException(
                code: Code::TABLE_NOT_FOUND,
                message: "Schema not loaded for table `{$table}` (model " . $model::class . ') — check table exists and connection has SELECT grant',
                category: 'audit/schema',
            );
        }
        $deleteFlg = Field::deleteFlg($allCols);
        $exposed   = array_values(array_filter(
            $allCols,
            static fn (string $c): bool => $c !== 'delete_time' && $c !== $deleteFlg,
        ));
        return '`' . implode('`, `', $exposed) . '`';
    }
}

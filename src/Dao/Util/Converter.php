<?php

declare(strict_types=1);

namespace Hf3\Dao\Util;

use Hf3\Dao\Base\Listing;
use Hf3\Model\BaseModel;

final class Converter
{
    /**
     * 由 Model FQCN 推导其对应的 Listing 拼装工具类 FQCN
     *
     * @param BaseModel $model
     * @return class-string<Listing>
     */
    public static function modelToUtil(BaseModel $model): string
    {
        return str_replace('\\Model\\', '\\Util\\', $model::class) . '\\Dao\\Listing';
    }

    /**
     * 由 Listing 拼装工具类 FQCN 反推其对应的 Model FQCN —— listingUtilClass 的逆变换
     *
     * @param class-string<Listing> $listing
     * @return class-string<BaseModel>
     */
    public static function listingToModel(string $listing): string
    {
        $prefix = (string) preg_replace('/\\\\Dao\\\\Listing$/', '', $listing);
        return str_replace('\\Util\\', '\\Model\\', $prefix);
    }
}

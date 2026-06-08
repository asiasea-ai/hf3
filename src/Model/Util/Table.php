<?php

declare(strict_types=1);

namespace Hf3\Model\Util;

use Hf3\Code\Code;
use Hf3\Throwable\Exception\ErrorException;

final class Table
{
    /**
     * Model FQCN → 物理表名 —— 读 NAME 常量,缺则抛错;PART 分表后续接 PartTable
     * @param class-string $modelClass
     * @return string
     */
    public static function get(string $modelClass): string
    {
        if (!defined("{$modelClass}::NAME")) {
            throw new ErrorException(
                code: Code::TABLE_NOT_FOUND,
                message: "Model {$modelClass} 缺少 NAME 常量",
                category: 'model/table',
            );
        }
        return (string) constant("{$modelClass}::NAME");
    }
}

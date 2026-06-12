<?php

declare(strict_types=1);

namespace Hf3\Model\Util;

use Hf3\Context\Request;
use Hf3\Throwable\Exception\ErrorException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class Auto
{
    /**
     * time —— 由 Model FQCN 取表列名 list,表实际含 etc 配置的时间列时返 [列名 => now],否则返空
     * @param class-string $model Model FQCN
     * @param string $kind 事件类型(create_time / update_time / delete_time)
     * @return array
     */
    public static function time(string $model, string $kind): array
    {
        /** 配置的时间列名 */
        $field = (string) (etc('auto.time.' . $kind) ?? '');
        if (superEmpty($field)) {
            return [];
        }

        /** 表实际含该列才注入 */
        $columns = $model::fieldList();
        if (!in_array($field, $columns, true)) {
            return [];
        }

        return [$field => date('Y-m-d H:i:s')];
    }

    /**
     * accountId —— 由 Model FQCN 取表列名 list,表实际含 etc 配置的账号列时返 [列名 => 当前用户 id],否则返空
     * @param class-string $model Model FQCN
     * @param string $kind 事件类型(create / update / delete)
     * @return array
     */
    public static function accountId(string $model, string $kind): array
    {
        /** 配置的账号列名 */
        $field = (string) (etc('auto.account.' . $kind) ?? '');
        if (superEmpty($field)) {
            return [];
        }

        /** 表实际含该列才注入 */
        $columns = $model::fieldList();
        if (!in_array($field, $columns, true)) {
            return [];
        }

        return [$field => Request::getAccountId()];
    }

    /**
     * timeField —— 时间族审计列名 list(etc auto.time 各 kind 的列名值)
     * @return list<string>
     */
    public static function timeField(): array
    {
        $cols = (array) (etc('auto.time') ?? []);
        return array_values($cols);
    }

    /**
     * accountField —— 账号族审计列名 list(etc auto.account 各 kind 的列名值)
     * @return list<string>
     */
    public static function accountField(): array
    {
        $cols = (array) (etc('auto.account') ?? []);
        return array_values($cols);
    }
}

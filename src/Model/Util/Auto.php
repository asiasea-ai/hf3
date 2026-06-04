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
     * time —— $columns 是表列名 list,按 etc 候选求交集填 now
     * @param list<string> $columns
     * @param string $kind
     * @return array
     */
    public static function time(array $columns, string $kind): array
    {
        $candidates = etc('auto.time.' . $kind) ?? [];
        if (!is_array($candidates)) {
            return [];
        }

        $matched = array_values(array_filter(
            $candidates,
            static fn ($col): bool => in_array((string) $col, $columns, true),
        ));
        if ($matched === []) {
            return [];
        }

        return array_fill_keys($matched, date('Y-m-d H:i:s'));
    }

    /**
     * accountId —— $columns 是表列名 list,按 etc 候选求交集填当前用户 id
     * @param list<string> $columns
     * @param string $kind
     * @return array
     */
    public static function accountId(array $columns, string $kind): array
    {
        $candidates = etc('auto.account.' . $kind) ?? [];
        if (!is_array($candidates)) {
            return [];
        }

        $matched = array_values(array_filter(
            $candidates,
            static fn ($col): bool => in_array((string) $col, $columns, true),
        ));
        if ($matched === []) {
            return [];
        }

        return array_fill_keys($matched, Request::getAccountId());
    }

    /**
     * managed —— 全部由框架强制接管的审计字段名(time + account 两族,各 kind 候选列名 union).
     *
     * BaseModel::save/update 等写入路径用本方法去过滤掉外部 $data 里的同名字段——
     * 即使前端误传 update_time / delete_time / create_account_id 也直接丢弃,
     * 紧接着 Auto::time / Auto::accountId 按当前 op 注入正确的审计字段值.
     * @return list<string>
     */
    public static function managed(): array
    {
        $cols = [];
        foreach ((array) (etc('auto.time') ?? []) as $candidates) {
            foreach ((array) $candidates as $col) {
                $cols[] = (string) $col;
            }
        }
        foreach ((array) (etc('auto.account') ?? []) as $candidates) {
            foreach ((array) $candidates as $col) {
                $cols[] = (string) $col;
            }
        }
        return array_values(array_unique($cols));
    }
}

<?php

declare(strict_types=1);

namespace Hf3\Middleware\Util;

/**
 * URL 业务 ring 前缀匹配工具.
 */
final class Ring
{
    private const array PREFIXES = ['/web/', '/App/', '/rpc/'];

    /**
     * 路径是否落在业务 ring 内
     * @param string $path
     * @return bool
     */
    public static function isBusiness(string $path): bool
    {
        foreach (Ring::PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        return false;
    }
}

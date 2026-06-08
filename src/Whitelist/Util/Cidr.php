<?php

declare(strict_types=1);

namespace Hf3\Whitelist\Util;

final class Cidr
{
    /**
     * 判断 IP 是否落在指定网段内
     * @param string $cidr 网段, 支持纯 IP 精确匹配或 CIDR 形式如 10.0.0.0/8
     * @param string $ip 待判定的 IPv4 地址
     * @return bool 命中网段返回 true; 非法 IP 返回 false
     */
    public static function contains(string $cidr, string $ip): bool
    {
        if (!str_contains($cidr, '/')) {
            return $cidr === $ip;
        }
        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;
        if ($bits === 0) {
            return true;
        }
        $subnetLong = ip2long($subnet);
        $ipLong     = ip2long($ip);
        if ($subnetLong === false || $ipLong === false) {
            return false;
        }
        $mask = -1 << (32 - $bits);
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}

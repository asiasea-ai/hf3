<?php

declare(strict_types=1);

namespace Hf3\Whitelist\Util;

final class Cidr
{
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

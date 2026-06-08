<?php

declare(strict_types=1);

use Hf3\Code\Code;
use Hf3\Throwable\Exception\ErrorException;

/**
 * 按点号路径读取 App/Etc 下的业务配置, 形如 etc('iam.oidc') → App/Etc/iam/oidc.php
 * @param string $path 点号分隔路径, 至少两段 <dir>.<file>, 可继续往深取数组键
 * @param mixed|null $default 路径不存在时的回退值
 * @return mixed 命中的配置值, 未命中返 $default
 */
function etc(string $path, mixed $default = null): mixed
{
    static $cache = [];

    $segs = explode('.', $path);
    if (count($segs) < 2) {
        throw new ErrorException(
            code: Code::ETC_PATH_INVALID,
            message: "etc('{$path}'): path 至少要 2 段 <dir>.<file>,例 'iam.oidc' → App/Etc/iam/oidc.php",
            category: 'etc',
        );
    }

    $abs = null;
    $matchedTake = 0;
    for ($take = count($segs); $take >= 2; $take--) {
        $head = array_slice($segs, 0, $take);
        $candidate = BASE_PATH . '/App/Etc/' . implode('/', array_slice($head, 0, -1)) . '/' . end($head) . '.php';
        if (is_file($candidate)) {
            $abs = $candidate;
            $matchedTake = $take;
            break;
        }
    }
    if ($abs === null) {
        $shallow = BASE_PATH . '/App/Etc/' . $segs[0] . '/' . $segs[1] . '.php';
        throw new ErrorException(
            code: Code::ETC_FILE_NOT_FOUND,
            message: "etc('{$path}'): 配置文件不存在 {$shallow}",
            category: 'etc',
        );
    }

    $cacheKey = implode('/', array_slice($segs, 0, $matchedTake));
    $remain   = array_slice($segs, $matchedTake);

    if (!isset($cache[$cacheKey])) {
        $loaded = require $abs;
        if (!is_array($loaded)) {
            throw new ErrorException(
                code: Code::ETC_FILE_FORMAT_INVALID,
                message: "etc('{$path}'): {$abs} 必须 return array,实际返 " . gettype($loaded),
                category: 'etc',
            );
        }
        $cache[$cacheKey] = $loaded;
    }

    if ($remain === []) {
        return $cache[$cacheKey];
    }

    $value = $cache[$cacheKey];
    foreach ($remain as $seg) {
        if (!is_array($value) || !array_key_exists($seg, $value)) {
            return $default;
        }
        $value = $value[$seg];
    }

    return $value;
}

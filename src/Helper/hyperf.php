<?php

declare(strict_types=1);

/**
 * Hyperf 工具函数 → 全局命名空间桥接.
 *
 * Hyperf\Support 下的函数在命名空间内, 项目文件必须 `use function` 才能用.
 * 这里把常用的挂到全局, Composer autoload 后无需任何 import 直接调用.
 */

/** @see \Hyperf\Support\env */
function env(string $key, mixed $default = null): mixed
{
    return \Hyperf\Support\env($key, $default);
}

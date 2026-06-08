<?php

declare(strict_types=1);

/**
 * 把 Hyperf\Support 下常用函数桥接到全局命名空间, autoload 后无需 use function 直接调用.
 */

/**
 * 读取环境变量, 桥接 Hyperf\Support\env
 * @param string $key 环境变量名
 * @param mixed|null $default 缺省值
 * @return mixed 变量值, 不存在返 $default
 * @see \Hyperf\Support\env
 */
function env(string $key, mixed $default = null): mixed
{
    return \Hyperf\Support\env($key, $default);
}

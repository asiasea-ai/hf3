<?php

declare(strict_types=1);

/**
 * 比 empty() 更严格的空判断, 把 [0=>''] 这类伪空数组也视为空, 但保留 0 / '0' 为非空
 * @param mixed $value 待判定的值
 * @return bool 视为空返回 true
 */
function superEmpty(mixed $value): bool
{
    if (is_array($value)) {
        if (count($value) === 1 && isset($value[0]) && $value[0] !== 0 && $value[0] !== '0' && empty($value[0])) {
            unset($value[0]);
        }
        return $value === [];
    }

    if (is_object($value)) {
        return (array) $value === [];
    }

    if ($value === 0 || $value === '0') {
        return false;
    }
    return empty($value);
}

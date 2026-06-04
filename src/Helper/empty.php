<?php

declare(strict_types=1);

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

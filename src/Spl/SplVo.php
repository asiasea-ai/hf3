<?php

declare(strict_types=1);

namespace Hf3\Spl;

use Hf3\Spl\Util\CtorMeta;

readonly class SplVo
{
    /**
     * 转为数组
     * @return array
     */
    public function toArray(): array
    {
        return (array) $this;
    }

    /**
     * 从数据行创建 VO —— 按构造器参数类型自动 cast(string/int/float/bool),
     * caller 不需提前调 Adapter::castRow;null 保留 null;缺失 key 走构造器默认值
     * @param array $row
     * @return static
     */
    public static function fromRow(array $row): static
    {
        $meta = CtorMeta::of(static::class);
        if ($meta === null) {
            return new static();
        }

        $args = [];
        foreach ($meta as $name => $info) {
            if (!array_key_exists($name, $row)) {
                continue;
            }
            $val = $row[$name];
            if ($val === null) {
                $args[$name] = null;
                continue;
            }
            $args[$name] = match ($info['type']) {
                'string' => (string) $val,
                'int' => (int) $val,
                'float' => (float) $val,
                'bool' => is_bool($val) ? $val : filter_var($val, FILTER_VALIDATE_BOOLEAN),
                default => $val,
            };
        }

        return new static(...$args);
    }
}

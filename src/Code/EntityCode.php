<?php

declare(strict_types=1);

namespace Hf3\Code;

interface EntityCode extends CodeInterface
{
    /**
     * 实体名称
     * @return string
     */
    public static function entityName(): string;

    /**
     * 未找到实体
     * @return self
     */
    public static function notFound(): self;
}

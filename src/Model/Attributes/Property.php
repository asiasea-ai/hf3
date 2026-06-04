<?php

declare(strict_types=1);

namespace Hf3\Model\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Property
{
    private string $name = '';

    /**
     * 属性配置
     * @param bool $isPrimaryKey
     * @param bool $allowNull
     * @param string|int|float|null|bool $defaultValue
     * @param string|null $convertObject
     */
    public function __construct(
        public bool $isPrimaryKey = false,
        public bool $allowNull = false,
        public string|int|float|null|bool $defaultValue = null,
        public ?string $convertObject = null,
    ) {

    }

    /**
     * 获取属性名
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * 设置属性名
     * @param string $name
     * @return void
     */
    public function __setName(string $name): void
    {
        $this->name = $name;
    }
}

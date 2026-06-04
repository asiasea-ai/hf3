<?php

declare(strict_types=1);

namespace Hf3\Policy;

use Hyperf\Context\ApplicationContext;

/**
 * 轻量授权 Gate —— define/allows 模式,forUser 浅克隆协程安全.
 */
final class Gate
{
    /** @var array<string, callable|string> */
    private array $abilities = [];

    private ?object $user = null;

    /**
     * 注册 ability
     *
     * @param string $ability ability 名称
     * @param callable|string $callback 可调用闭包或可调用类名(由 DI 解析)
     * @return void
     */
    public function define(string $ability, callable|string $callback): void
    {
        $this->abilities[$ability] = $callback;
    }

    /**
     * 返回绑定了指定用户的新 Gate 实例(浅克隆)
     *
     * @param ?object $user 传 null 表示 guest 身份
     * @return static
     */
    public function forUser(?object $user): static
    {
        $gate       = clone $this;
        $gate->user = $user;
        return $gate;
    }

    /**
     * 检查 ability 是否被允许
     *
     * @param string $ability
     * @param array $arguments 传给回调的额外参数(展开为独立参数)
     * @return bool
     */
    public function allows(string $ability, array $arguments = []): bool
    {
        if (!array_key_exists($ability, $this->abilities)) {
            return false;
        }
        $callback = $this->abilities[$ability];
        if (is_string($callback)) {
            $callback = ApplicationContext::getContainer()->get($callback);
        }
        return (bool) $callback($this->user, $ability, $arguments);
    }

    /**
     * 检查 ability 是否被拒绝
     *
     * @param string $ability
     * @param array $arguments
     * @return bool
     */
    public function denies(string $ability, array $arguments = []): bool
    {
        return !$this->allows($ability, $arguments);
    }
}

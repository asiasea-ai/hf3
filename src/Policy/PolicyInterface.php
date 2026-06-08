<?php

declare(strict_types=1);

namespace Hf3\Policy;

/**
 * Gate 策略契约 —— 对某 ability 表态:true 允许 / false 拒绝 / null 不表态(交给下一个策略).
 */
interface PolicyInterface
{
    /**
     * 对一次授权请求表态
     * @param object|null $user 当前用户,guest 为 null
     * @param string $ability ability 名,如 'oidc.bypass'
     * @param array $args 上下文参数,如 [$method, $path]
     * @return bool|null true 允许 / false 拒绝 / null 不表态交给下一个
     */
    public function __invoke(?object $user, string $ability, array $args): ?bool;
}

<?php

declare(strict_types=1);

namespace Hf3\Listener;

use Hf3\Policy\Gate;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;

/**
 * 启动时从 config/autoload/policy.php 读取 ability→policy 映射, 批量注册到 Gate.
 */
#[Listener]
final class PolicyRegister implements ListenerInterface
{
    public function __construct(
        private readonly Gate $gate,
        private readonly ConfigInterface $config,
    ) {
    }

    public function listen(): array
    {
        return [BootApplication::class];
    }

    public function process(object $event): void
    {
        $abilities = (array) $this->config->get('policy', []);
        foreach ($abilities as $ability => $policyClass) {
            if (is_string($ability) && is_string($policyClass) && $policyClass !== '') {
                $this->gate->define($ability, $policyClass);
            }
        }
    }
}

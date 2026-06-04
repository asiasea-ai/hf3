<?php

declare(strict_types=1);

namespace Hf3\Throwable\Util;

use Hyperf\Context\ApplicationContext;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * Hyperf LoggerInterface 获取工具 —— 容器未就绪 / Factory 未注册时返 null.
 */
final class HyperfLogger
{
    /**
     * 取指定 channel + group 的 logger,失败返 null(让 caller fallback STDERR 之类)
     * @param string $channel
     * @param string $group
     * @return LoggerInterface|null
     */
    public static function get(string $channel = 'hyperf', string $group = 'default'): ?LoggerInterface
    {
        try {
            $container = ApplicationContext::getContainer();
            if (!$container->has(LoggerFactory::class)) {
                return null;
            }
            $factory = $container->get(LoggerFactory::class);
            return $factory->get($channel, $group);
        } catch (\Throwable) {
            return null;
        }
    }
}

<?php

declare(strict_types=1);

use Hyperf\Context\ApplicationContext;
use Hyperf\Redis\Redis;
use Hyperf\Redis\RedisFactory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * 获取 Redis 连接
 * @param string $pool 连接池名, 默认 'default'
 * @return Redis
 */
function redis(string $pool = 'default'): Redis
{
    $container = ApplicationContext::getContainer();
    return $container->get(RedisFactory::class)->get($pool);
}

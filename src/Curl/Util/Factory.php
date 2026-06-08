<?php

declare(strict_types=1);

namespace Hf3\Curl\Util;

use GuzzleHttp\Client as GuzzleClient;
use Hyperf\Context\ApplicationContext;
use Hyperf\Guzzle\ClientFactory;

/**
 * 建带默认超时的协程 Guzzle 客户端 —— handler 由 Hyperf ClientFactory 自动注入.
 */
final class Factory
{
    /** 默认整体超时(秒) */
    public const float DEFAULT_TIMEOUT = 3.0;

    /** 默认建连超时(秒) */
    public const float DEFAULT_CONNECT_TIMEOUT = 2.0;

    /**
     * 创建客户端 —— 默认超时收口于此,传入 config 可覆盖
     * @param array $config 覆盖默认的客户端配置
     * @return GuzzleClient
     */
    public static function make(array $config = []): GuzzleClient
    {
        $defaults = [
            'timeout' => Factory::DEFAULT_TIMEOUT,
            'connect_timeout' => Factory::DEFAULT_CONNECT_TIMEOUT,
        ];
        $merged = array_merge($defaults, $config);
        $container = ApplicationContext::getContainer();
        $factory = $container->get(ClientFactory::class);
        $client = $factory->create($merged);
        return $client;
    }
}

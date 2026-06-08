<?php

declare(strict_types=1);

namespace Hf3\Curl;

use Hf3\Curl\Util\Envelope;
use Hf3\Throwable\Exception\ErrorException;

/**
 * 内部服务调用器 —— 调通用客户端后拆 Hf3 信封,返回 result.data 负载.
 */
final class Rpc
{
    /**
     * 调内部服务 —— 业务码非成功即透传远程 code/msg,成功则返回拆信封后的负载
     * @param string $method HTTP 方法
     * @param string $uri 目标地址
     * @param array $options Guzzle 请求选项
     * @return array 拆信封后的 result.data 负载
     * @throws ErrorException 传输失败 / 响应非 JSON / 业务码非成功
     */
    public static function request(string $method, string $uri, array $options = []): array
    {
        $body = Client::request($method, $uri, $options);
        $payload = Envelope::unwrap($body);
        return $payload;
    }

}

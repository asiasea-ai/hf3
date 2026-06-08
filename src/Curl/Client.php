<?php

declare(strict_types=1);

namespace Hf3\Curl;

use Hf3\Code\Code;
use Hf3\Curl\Util\Factory;
use Hf3\Throwable\Exception\ErrorException;

/**
 * 通用 HTTP 客户端 —— 发请求并返回 JSON 解码后的数组,不感知业务信封.
 */
final class Client
{
    /**
     * 发起请求 —— 协程 handler 由 Factory 自动注入,失败抛 ErrorException
     * @param string $method HTTP 方法(GET/POST/PUT/DELETE...)
     * @param string $uri 目标地址
     * @param array $options Guzzle 请求选项(form_params / json / headers / query / timeout 等)
     * @return array JSON 解码后的响应体
     * @throws ErrorException 传输失败或响应非 JSON
     */
    public static function request(string $method, string $uri, array $options = []): array
    {
        $client = Factory::make();
        try {
            $response = $client->request($method, $uri, $options);
        } catch (\Throwable $e) {
            throw new ErrorException(
                code: Code::CURL_TRANSPORT_FAIL,
                message: $e->getMessage(),
                category: 'curl',
                previous: $e,
            );
        }

        $raw = (string) $response->getBody();
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new ErrorException(code: Code::CURL_HTTP_NOT_OK, message: '远程响应非 JSON', category: 'curl');
        }
        return $decoded;
    }
}

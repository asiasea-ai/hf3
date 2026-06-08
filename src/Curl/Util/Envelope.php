<?php

declare(strict_types=1);

namespace Hf3\Curl\Util;

use Hf3\Code\Code;
use Hf3\Throwable\Exception\ErrorException;
use Hf3\Vo\Json;

/**
 * 拆解 Hf3 响应信封 —— 校验业务码后取出 result.data 负载.
 */
final class Envelope
{
    /**
     * 拆信封 —— 业务码非成功即透传远程 code/msg,成功则返回 result.data 负载
     * @param array $body 已 JSON 解码的响应体
     * @return array 拆出的负载(result.data ?? data ?? body)
     * @throws ErrorException 业务码非成功或负载非数组
     */
    public static function unwrap(array $body): array
    {
        $code = (int) ($body['code'] ?? Json::DEFAULT_OK_CODE);
        if (!Json::isSuccess($code)) {
            $msg = (string) ($body['msg'] ?? '');
            if ($msg === '') {
                $msg = '未知错误';
            }
            $detail = $code . ' ' . $msg;
            throw new ErrorException(code: Code::CURL_HTTP_NOT_OK, message: $detail, category: 'curl');
        }

        $payload = $body['result']['data'] ?? $body['data'] ?? $body;
        if (!is_array($payload)) {
            throw new ErrorException(code: Code::CURL_HTTP_NOT_OK, message: '远程响应负载非数组', category: 'curl');
        }
        return $payload;
    }
}

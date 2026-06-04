<?php

declare(strict_types=1);

namespace Hf3\Identity\Util;

/**
 * 身份请求头读取 —— 逻辑键 client / token 映射到各 app 配置的真实头名(etc('iam.header.*')).
 */
final class Header
{
    /**
     * 从请求头取客户端标识(逻辑键 client → 真实头名由配置决定)
     * @param array $headers
     * @return string|null
     */
    public static function client(array $headers): ?string
    {
        $key = strtolower(Header::clientKey());
        $headers = array_change_key_case($headers, CASE_LOWER);
        $value = $headers[$key] ?? null;
        $first = is_array($value) ? ($value[0] ?? null) : $value;

        return ($first === null || $first === '') ? null : (string) $first;
    }

    /**
     * 从请求头取会话 token(逻辑键 token)
     * @param array $headers
     * @return string|null
     */
    public static function token(array $headers): ?string
    {
        /** 取配置头名对应的原始头值 */
        $key = strtolower(Header::tokenKey());
        $headers = array_change_key_case($headers, CASE_LOWER);
        $value = $headers[$key] ?? null;
        $first = is_array($value) ? ($value[0] ?? null) : $value;
        if ($first === null || $first === '') {
            return null;
        }

        /** 兼容标准 Authorization: Bearer <token> —— 剥掉 Bearer 前缀只留裸 token */
        $raw = (string) $first;
        $token = str_starts_with($raw, 'Bearer ') ? substr($raw, 7) : $raw;

        return $token === '' ? null : $token;
    }

    /**
     * 客户端标识的真实头名
     * @return string
     */
    public static function clientKey(): string
    {
        return (string) etc('iam.header.client');
    }

    /**
     * 会话 token 的真实头名
     * @return string
     */
    public static function tokenKey(): string
    {
        return (string) etc('iam.header.token');
    }
}

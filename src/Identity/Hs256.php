<?php

declare(strict_types=1);

namespace Hf3\Identity;

use Firebase\JWT\JWT as FirebaseJwt;
use Firebase\JWT\Key;
use Hf3\Code\Code;
use Hf3\Throwable\Exception\ErrorException;
use Hf3\Throwable\Exception\WarnException;

/**
 * HS256 自签会话 —— 应用用对称密钥自己签自己验,参数由调用方显式传入.
 */
final class Hs256
{
    /**
     * 验签 + iss + aud 校验
     * @param string $token
     * @param string $secret 对称密钥
     * @param string $algo 签名算法(HS256)
     * @param int $leeway 过期容差秒数
     * @param string $iss 期望的 iss(空串跳过)
     * @param string $aud 期望的 aud(空串跳过)
     * @return array claims
     * @throws WarnException
     */
    public static function verify(string $token, string $secret, string $algo, int $leeway, string $iss, string $aud): array
    {
        if ($secret === '') {
            throw new WarnException(code: Code::AUTH_FAILED, message: 'oidc secret 未配置');
        }

        FirebaseJwt::$leeway = $leeway;
        try {
            $key     = new Key($secret, $algo);
            $payload = (array) FirebaseJwt::decode($token, $key);
        } catch (\Throwable $e) {
            throw new WarnException(code: Code::AUTH_FAILED, message: 'token 解码失败: ' . $e->getMessage());
        }

        /** 校验 iss */
        if ($iss !== '' && ($payload['iss'] ?? '') !== $iss) {
            throw new WarnException(code: Code::AUTH_FAILED, message: 'token iss 不匹配');
        }

        /** 校验 aud */
        if ($aud !== '' && ($payload['aud'] ?? '') !== $aud) {
            throw new WarnException(code: Code::AUTH_FAILED, message: 'token aud 不匹配');
        }

        return $payload;
    }

    /**
     * 用对称密钥签发 token(补 iat/exp/iss/aud)
     * @param array $payload
     * @param string $secret 对称密钥
     * @param string $algo 签名算法(HS256)
     * @param int $ttl 有效期秒数
     * @param string $iss 签发者
     * @param string $aud 受众
     * @return string
     * @throws ErrorException
     */
    public static function sign(array $payload, string $secret, string $algo, int $ttl, string $iss, string $aud): string
    {
        if ($secret === '') {
            throw new ErrorException(
                code: Code::ETC_FILE_FORMAT_INVALID,
                message: 'oidc secret 未配置,无法签发',
                category: 'oidc/config',
            );
        }

        $now = time();
        $payload += [
            'iat' => $now,
            'exp' => $now + $ttl,
            'iss' => $iss,
            'aud' => $aud,
        ];

        return FirebaseJwt::encode($payload, $secret, $algo);
    }
}

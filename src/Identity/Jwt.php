<?php

namespace Hf3\Identity;

use App\Lib\Oidc\Util\Jwks;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT as FirebaseJwt;
use Hf3\Code\Code;
use Hf3\Identity\Jwt as IdentityJwt;
use Hf3\Identity\Util\Header;
use Hf3\Throwable\Exception\ErrorException;
use Hf3\Throwable\Exception\WarnException;
use Psr\Http\Message\ServerRequestInterface;

class Jwt
{
    public static function getKid(string $token): ?string
    {
        /** 不验签先窥视 header.kid */
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new WarnException(code: Code::AUTH_FAILED, message: 'token 格式非法');
        }
        $header = json_decode((string)base64_decode(strtr($parts[0], '-_', '+/')), true);
        return is_array($header) ? ($header['kid'] ?? null) : null;
    }

    /**
     * 验证 oidc
     * @param string|null $token
     * @param string|null $clientId
     * @param array $keys
     * @return array
     * @throws ErrorException
     * @throws WarnException
     */
    public static function verify(?string $token, ?string $clientId, array $keys): array
    {
        /** $clientId & $token 验证 */
        if ($clientId === null) {
            throw new WarnException(code: Code::AUTH_REQUIRED, message: '缺 ' . Header::clientKey() . ' header');
        }
        if ($token === null) {
            throw new WarnException(code: Code::AUTH_REQUIRED, message: '缺 token');
        }

        /** 取 kid */
        $kid = IdentityJwt::getKid($token);
        if ($kid === null) {
            throw new WarnException(code: Code::AUTH_FAILED, message: 'token 缺 kid');
        }

        /** 取公钥集合(kid => Key),kid 不在则强刷一次 */
        if (!isset($keys[$kid])) {
            $keys = Jwks::keys(true);
        }
        if (!isset($keys[$kid])) {
            throw new WarnException(code: Code::AUTH_FAILED, message: "验签失败:jwks 无匹配 kid({$kid})");
        }

        try {
            $payload = (array)FirebaseJwt::decode($token, $keys);
        } catch (ExpiredException) {
            throw new WarnException(code: Code::AUTH_FAILED, message: 'token 已过期');
        } catch (\Throwable $e) {
            throw new WarnException(code: Code::AUTH_FAILED, message: 'token 验签失败: ' . $e->getMessage());
        }

        /** 从配置取该 client 的期望 iss / aud(共享 jwks 底座) */
        $clientKey = strtolower($clientId);
        $jwks = (array)etc("iam.oidc.clients.{$clientKey}.jwks");
        $issuer = (string)($jwks['issuer'] ?? '');
        $audience = (string)($jwks['audience'] ?? '');

        /** 校验 iss */
        if ($issuer !== '' && ($payload['iss'] ?? '') !== $issuer) {
            throw new WarnException(code: Code::AUTH_FAILED, message: 'token iss 不匹配');
        }

        /** 校验 aud */
        if ($audience !== '' && ($payload['aud'] ?? '') !== $audience) {
            throw new WarnException(code: Code::AUTH_FAILED, message: 'token aud 不匹配');
        }

        return $payload;
    }
}
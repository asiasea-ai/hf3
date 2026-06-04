<?php

declare(strict_types=1);

namespace Hf3\Context;

use Hyperf\Context\Context;
use Godruoyi\Snowflake\Snowflake;

final class Request
{
    private const string KEY_TRACE_ID   = 'es37_trace_id';
    private const string KEY_STARTED_AT = 'es37_started_at';
    private const string KEY_ACCOUNT_ID = 'es37_account_id';
    private const string KEY_IDENTITY   = 'es37_identity';

    /**
     * 初始化请求上下文
     * @return void
     */
    public static function start(): void
    {
        Context::set(Request::KEY_TRACE_ID, (string) (new Snowflake())->id());
        Context::set(Request::KEY_STARTED_AT, microtime(true));
    }

    /**
     * 当前请求 traceId
     * @return string
     */
    public static function traceId(): string
    {
        return (string) (Context::get(Request::KEY_TRACE_ID) ?? '');
    }

    /**
     * 请求耗时(毫秒)
     * @return float
     */
    public static function processTimeMs(): float
    {
        $startedAt = Context::get(Request::KEY_STARTED_AT);
        if (!is_float($startedAt)) {
            return 0.0;
        }
        return round((microtime(true) - $startedAt) * 1000, 3);
    }

    /**
     * 设置 accountId
     * @param int $accountId
     * @return void
     */
    public static function setAccountId(int $accountId): void
    {
        Context::set(Request::KEY_ACCOUNT_ID, $accountId);
    }

    /**
     * 获取 accountId
     * @return int
     */
    public static function getAccountId(): int
    {
        return (int) (Context::get(Request::KEY_ACCOUNT_ID) ?? 0);
    }

    /**
     * 设置身份对象
     * @param object $identity
     * @return void
     */
    public static function setIdentity(object $identity): void
    {
        Context::set(Request::KEY_IDENTITY, $identity);
    }

    /**
     * 获取身份对象
     * @return object|null
     */
    public static function identity(): ?object
    {
        $v = Context::get(Request::KEY_IDENTITY);
        return is_object($v) ? $v : null;
    }

    /**
     * 获取请求快照
     * @return array
     */
    public static function snapshot(): array
    {
        $startedAt = Context::get(Request::KEY_STARTED_AT);
        return [
            'trace_id'   => Request::traceId(),
            'started_at' => is_float($startedAt) ? $startedAt : null,
            'account_id' => Request::getAccountId(),
            'oidc'   => Request::identity(),
        ];
    }

    /**
     * 恢复请求快照
     * @param array $snapshot
     * @return void
     */
    public static function restore(array $snapshot): void
    {
        if (isset($snapshot['trace_id']) && $snapshot['trace_id'] !== '') {
            Context::set(Request::KEY_TRACE_ID, $snapshot['trace_id']);
        }
        if (isset($snapshot['started_at']) && is_float($snapshot['started_at'])) {
            Context::set(Request::KEY_STARTED_AT, $snapshot['started_at']);
        }
        Request::setAccountId((int) ($snapshot['account_id'] ?? 0));
        if (isset($snapshot['oidc']) && is_object($snapshot['oidc'])) {
            Request::setIdentity($snapshot['oidc']);
        }
    }
}

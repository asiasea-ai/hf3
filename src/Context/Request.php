<?php

declare(strict_types=1);

namespace Hf3\Context;

use Hyperf\Context\Context;
use Hyperf\Context\ApplicationContext;
use Hyperf\Snowflake\IdGeneratorInterface;

final class Request
{
    private const string KEY_TRACE_ID   = 'es37_trace_id';
    private const string KEY_STARTED_AT = 'es37_started_at';
    private const string KEY_ACCOUNT_ID = 'es37_account_id';
    private const string KEY_IDENTITY   = 'es37_identity';
    private const string KEY_SOURCE     = 'es37_source';

    /** HTTP 请求来源标识 */
    public const string SOURCE_HTTP = 'http';

    /**
     * 初始化上下文(HTTP 请求 / 定时任务 / 常驻进程) —— 生成新 traceId 与起始时间,并标记来源
     * @param string $source 来源标识,HTTP 默认 'http',任务/进程传 static::class
     * @return void
     */
    public static function start(string $source = Request::SOURCE_HTTP): void
    {
        $container = ApplicationContext::getContainer();
        $idGenerator = $container->get(IdGeneratorInterface::class);
        $traceId = (string) $idGenerator->generate();
        Context::set(Request::KEY_TRACE_ID, $traceId);
        Context::set(Request::KEY_STARTED_AT, microtime(true));
        Context::set(Request::KEY_SOURCE, $source);
    }

    /**
     * 当前 traceId,未初始化返回 '-1'
     * @return string
     */
    public static function traceId(): string
    {
        return (string) (Context::get(Request::KEY_TRACE_ID) ?? '-1');
    }

    /**
     * 当前上下文来源,优先 Context 标记;未标记则回落进程标题,再回落 'cli'
     * @return string
     */
    public static function source(): string
    {
        $source = Context::get(Request::KEY_SOURCE);
        if (is_string($source) && $source !== '') {
            return $source;
        }
        $title = cli_get_process_title();
        return is_string($title) && $title !== '' ? $title : 'cli';
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
            'source'     => Request::source(),
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
        if (isset($snapshot['source']) && is_string($snapshot['source']) && $snapshot['source'] !== '') {
            Context::set(Request::KEY_SOURCE, $snapshot['source']);
        }
        if (isset($snapshot['oidc']) && is_object($snapshot['oidc'])) {
            Request::setIdentity($snapshot['oidc']);
        }
    }
}

<?php

declare(strict_types=1);

namespace Hf3\Code;

enum Code: int implements CodeInterface
{
    case ACTION_NOT_FOUND = -404;
    case TABLE_NOT_FOUND = -704;

    case FIELD_NOT_FOUND = -8404;

    case METHOD_NOT_ALLOWED = -9405;

    case DTO_INVALID = -1001;

    case AUTH_REQUIRED = -401;

    case AUTH_FAILED = -4011;

    case RATE_LIMIT_EXCEEDED = -429;

    case CURL_TRANSPORT_FAIL = -2001;

    case CURL_HTTP_NOT_OK = -2002;

    case INTERNAL_ERROR = -1;

    case CONFIG_BULK_PREFIX_INVALID = -3201;
    case ETC_PATH_INVALID            = -3202;
    case ETC_FILE_NOT_FOUND          = -3203;
    case ETC_FILE_FORMAT_INVALID     = -3204;

    case ROUTE_DI_UNRESOLVABLE       = -3301;
    case ROUTE_FILE_FORMAT_INVALID   = -3302;

    case SQL_PARAM_COUNT_MISMATCH    = -3401;
    case SQL_PARAM_NOT_BOUND         = -3402;
    case SQL_INJECTION_SUSPECTED     = -3403;

    case MODEL_PK_IMMUTABLE          = -3501;
    case MODEL_FIND_MULTI_ROWS       = -3502;
    case MODEL_DELETE_FLG_AMBIGUOUS  = -3503;

    case MODEL_DELETE_FIELD_NULL = -3504;
    case MODEL_FIND_ONE_FIELD_NULL = -3505;
    case MODEL_SAVE_FIELD_NULL = -3506;

    case MODEL_SUBJECT_AMBIGUOUS     = -3507;
    case MODEL_SUBJECT_MISSING       = -3508;
    case MODEL_SUBJECT_SQL_UNGUARDED = -3509;

    case ADAPTER_FIELD_MISSING       = -3601;

    case COROUTINE_TIMEOUT           = -3701;

    case SIGN_KEY_MISSING            = -3801;
    case SIGN_KEY_INVALID            = -3802;

    case SIGN_TIMESTAMP_INVALID      = -3803;

    case WAF_BLOCKED                 = -3901;


    public function message(): string
    {
        return match ($this) {
            Code::ACTION_NOT_FOUND          => 'Action not found',
            Code::METHOD_NOT_ALLOWED        => 'Method not allowed',
            Code::DTO_INVALID               => 'Invalid request parameters',
            Code::AUTH_REQUIRED             => '未登录,请先登录',
            Code::AUTH_FAILED               => 'Token 无效或已过期',
            Code::RATE_LIMIT_EXCEEDED       => '操作太频繁,请稍后再试',
            Code::CURL_TRANSPORT_FAIL       => '远程网络访问失败',
            Code::CURL_HTTP_NOT_OK          => '远程网络返回非预期状态',
            Code::INTERNAL_ERROR            => '系统内部错误',
            Code::TABLE_NOT_FOUND           => '找不到对应表',
            Code::FIELD_NOT_FOUND           => '找不到对应字段',
            Code::CONFIG_BULK_PREFIX_INVALID => 'config() 批量取值 prefix 不合法',
            Code::ETC_PATH_INVALID          => 'etc() path 段数不足',
            Code::ETC_FILE_NOT_FOUND        => 'etc 配置文件不存在',
            Code::ETC_FILE_FORMAT_INVALID   => 'etc 配置文件必须 return array',
            Code::ROUTE_DI_UNRESOLVABLE     => 'Controller 构造器参数无法自动注入',
            Code::ROUTE_FILE_FORMAT_INVALID => 'Route 文件结构错',
            Code::SQL_PARAM_COUNT_MISMATCH  => 'SQL `?` 占位符数量与 params 数量不匹配',
            Code::SQL_PARAM_NOT_BOUND       => 'SQL 未走参数绑定(`=` 右侧应为 `?`)',
            Code::SQL_INJECTION_SUSPECTED   => 'SQL 注入嫌疑',
            Code::MODEL_PK_IMMUTABLE        => '不允许更新主键',
            Code::MODEL_DELETE_FIELD_NULL        => '未找到删除的字段',
            Code::MODEL_FIND_ONE_FIELD_NULL        => '未找到查找的字段',
            Code::MODEL_SAVE_FIELD_NULL        => '未找到新增的字段',
            Code::MODEL_FIND_MULTI_ROWS     => '单行接口命中多行,数据完整性问题',
            Code::MODEL_DELETE_FLG_AMBIGUOUS => 'FIELDS 同时命中多个软删候选列,schema 错配',
            Code::MODEL_SUBJECT_AMBIGUOUS   => '表同时命中多个主体隔离列,schema 错配',
            Code::MODEL_SUBJECT_MISSING     => '受管表操作缺少主体上下文,跨主体/系统操作请用 Subject::without 显式放行',
            Code::MODEL_SUBJECT_SQL_UNGUARDED => '裸 SQL 操作受管表但 SQL 未带主体列,数据隔离风险',
            Code::ADAPTER_FIELD_MISSING     => 'Adapter 源 VO 缺字段且目标构造器无 default',
            Code::COROUTINE_TIMEOUT         => 'waitGroup 超时',
            Code::SIGN_KEY_MISSING          => 'SIGN_RSA_PUBLIC 未配置',
            Code::SIGN_KEY_INVALID          => 'SIGN_RSA_PUBLIC PEM 格式错,解析失败',
            Code::SIGN_TIMESTAMP_INVALID    => '请求已过期或时间戳无效',
            Code::WAF_BLOCKED               => '请求被拒绝',
        };
    }
}

<?php

declare(strict_types=1);

namespace Hf3\Vo;

use Hf3\Context\Request;

final readonly class Json
{
    /** 成功码下限:>= 此值即视为成功,预留 100001/100002 表达"部分成功 / 异步排队中"等分级语义 */
    public const int SUCCESS_MIN = 100000;

    public const int DEFAULT_OK_CODE = 100000;

    public const string DEFAULT_OK_MSG = '执行成功';

    /**
     * 判定 code 是否表达成功(>= SUCCESS_MIN)
     * @param int $code
     * @return bool
     */
    public static function isSuccess(int $code): bool
    {
        return $code >= Json::SUCCESS_MIN;
    }

    /**
     * JSON 响应构造
     * @param int $code
     * @param mixed $data
     * @param string $msg
     */
    private function __construct(
        public int $code,
        public mixed $data,
        public string $msg,
    ) {
    }

    /**
     * 成功响应
     * @param mixed $data
     * @param string $msg
     * @return self
     */
    public static function ok(mixed $data = null, string $msg = Json::DEFAULT_OK_MSG): self
    {
        return new Json(Json::DEFAULT_OK_CODE, $data, $msg);
    }

    /**
     * 失败响应
     * @param int $code
     * @param string $msg
     * @return self
     */
    public static function fail(int $code, string $msg): self
    {
        return new Json($code, null, $msg);
    }

    /**
     * 自定义响应
     * @param int $code
     * @param mixed $data
     * @param string $msg
     * @return self
     */
    public static function of(int $code, mixed $data, string $msg): self
    {
        return new Json($code, $data, $msg);
    }

    /**
     * 转为 JSON 字符串
     * @return string
     */
    public function toJson(): string
    {
        return (string) json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 转为数组
     * @return array
     */
    public function toArray(): array
    {
        return [
            'code'         => $this->code,
            'result'       => ['data' => $this->data],
            'msg'          => $this->msg,
            'trace_id'     => Request::traceId(),
            'process_time' => sprintf('%.2fms', Request::processTimeMs()),
        ];
    }
}

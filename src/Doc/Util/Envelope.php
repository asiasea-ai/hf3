<?php

declare(strict_types=1);

namespace Hf3\Doc\Util;

/**
 * Hf3\Vo\Json 响应外壳 → OpenAPI schema 组件.
 */
final class Envelope
{
    public const string SUCCESS_REF = '#/components/schemas/HfEnvelope';

    public const string ERROR_REF = '#/components/schemas/HfErrorEnvelope';

    /**
     * 成功响应:用 allOf 把通用外壳 + 具体 data 字段(指向某 VO)合一份
     * @param array<string, mixed> $dataRef VO 的 $ref 片段
     * @return array<string, mixed>
     */
    public static function successResponse(array $dataRef): array
    {
        return [
            'description' => 'OK',
            'content' => [
                'application/json' => [
                    'schema' => [
                        'allOf' => [
                            ['$ref' => Envelope::SUCCESS_REF],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'result' => [
                                        'type' => 'object',
                                        'properties' => ['data' => $dataRef],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * 错误响应:固定外壳,data 为 null
     * @param string $description
     * @return array<string, mixed>
     */
    public static function errorResponse(string $description): array
    {
        return [
            'description' => $description,
            'content' => [
                'application/json' => [
                    'schema' => ['$ref' => Envelope::ERROR_REF],
                ],
            ],
        ];
    }

    /**
     * 注入 components.schemas 的两份固定 schema —— 调用方 merge 到 pool 里
     * @return array<string, array<string, mixed>>
     */
    public static function schemas(): array
    {
        return [
            'HfEnvelope' => [
                'type' => 'object',
                'description' => 'Hf3 成功响应外壳',
                'properties' => [
                    'code' => [
                        'type' => 'integer',
                        'example' => 100000,
                        'description' => '业务码:>= 100000 表示成功(100000=OK,可扩展 100001=部分成功/100002=异步排队 等);< 100000 表示失败',
                    ],
                    'result' => [
                        'type' => 'object',
                        'properties' => ['data' => ['nullable' => true]],
                    ],
                    'msg' => ['type' => 'string', 'example' => '执行成功'],
                    'trace_id' => ['type' => 'string'],
                    'process_time' => ['type' => 'string', 'example' => '12.34ms'],
                ],
                'required' => ['code', 'result', 'msg'],
            ],
            'HfErrorEnvelope' => [
                'type' => 'object',
                'description' => 'Hf3 错误响应外壳',
                'properties' => [
                    'code' => ['type' => 'integer', 'example' => -1001],
                    'result' => [
                        'type' => 'object',
                        'properties' => ['data' => ['nullable' => true, 'example' => null]],
                    ],
                    'msg' => ['type' => 'string', 'example' => '字段[xxx]参数错误'],
                    'trace_id' => ['type' => 'string'],
                    'process_time' => ['type' => 'string'],
                ],
                'required' => ['code', 'result', 'msg'],
            ],
        ];
    }
}

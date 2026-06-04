<?php

declare(strict_types=1);

namespace Hf3\Doc\Util;

use Hf3\Dto\Util\Label;
use Hf3\Identity\Util\Header;
use ReflectionClass;

/**
 * DTO ctor → OpenAPI parameter/requestBody 片段映射.
 */
final class DtoMap
{

    /**
     * 把 DTO 拆成 path/query/body/header 四类 OpenAPI 片段.
     * @param class-string $dtoClass
     * @param string $httpMethod GET / POST / PUT / DELETE
     * @param array<int, string> $pathPlaceholders 路由占位符名字列表,如 ['id']
     * @return array{
     *     path:   array<int, array<string, mixed>>,
     *     query:  array<int, array<string, mixed>>,
     *     header: array<int, array<string, mixed>>,
     *     body:   array<string, mixed>|null
     * }
     */
    public static function split(string $dtoClass, string $httpMethod, array $pathPlaceholders): array
    {
        $rc = new ReflectionClass($dtoClass);
        $ctor = $rc->getConstructor();
        if ($ctor === null) {
            return ['path' => [], 'query' => [], 'header' => [], 'body' => null];
        }

        $useBody = in_array(strtoupper($httpMethod), ['POST', 'PUT', 'PATCH'], true);

        $path = [];
        $query = [];
        $header = [];
        $bodyProps = [];
        $bodyRequired = [];

        foreach ($ctor->getParameters() as $p) {
            $name = $p->getName();
            $schema = DtoMap::buildFieldSchema($dtoClass, $p);

            $description = $schema['__description'] ?? null;
            unset($schema['__description']);
            $required = (bool) ($schema['__required'] ?? false);
            unset($schema['__required']);

            $location = DtoMap::locationOf($name, $pathPlaceholders, $useBody);

            if ($location === 'body') {
                $bodyProps[$name] = $schema;
                if ($required) {
                    $bodyRequired[] = $name;
                }
                continue;
            }

            $param = ['name' => $name, 'in' => $location, 'schema' => $schema];
            if ($description !== null && $description !== '') {
                $param['description'] = $description;
            }
            // path 参数 OpenAPI 强制 required
            if ($location === 'path' || $required) {
                $param['required'] = true;
            }
            ${$location}[] = $param;
        }

        $body = null;
        if ($useBody && $bodyProps !== []) {
            $body = [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => $bodyProps,
                            'required' => $bodyRequired === [] ? null : array_values($bodyRequired),
                        ],
                    ],
                ],
            ];
            if ($body['content']['application/json']['schema']['required'] === null) {
                unset($body['content']['application/json']['schema']['required']);
            }
        }

        return ['path' => $path, 'query' => $query, 'header' => $header, 'body' => $body];
    }

    /**
     * 一个 promoted 参数 → OpenAPI schema 片段(含 __description / __required 内部标记)
     * @param string $dtoClass
     * @param \ReflectionParameter $p
     * @return array<string, mixed>
     */
    private static function buildFieldSchema(string $dtoClass, \ReflectionParameter $p): array
    {
        $name = $p->getName();
        $schema = Type::fromReflection($p->getType());

        if ($p->isDefaultValueAvailable()) {
            $default = $p->getDefaultValue();
            if ($default !== null) {
                $schema['default'] = $default;
            }
        }

        $assertAttrs = $p->getAttributes();
        $assertResult = AssertMap::fromAttributes($assertAttrs);
        $schema = array_merge($schema, $assertResult['constraints']);

        $description = Label::of($dtoClass, $name);
        if ($description !== null) {
            $schema['__description'] = $description;
        }

        $required = $assertResult['required'] || !$p->isDefaultValueAvailable();
        if ($required) {
            $schema['__required'] = true;
        }

        return $schema;
    }

    /**
     * 决定字段去向 —— header 优先于 path,path 优先于 body/query
     * @param string $name
     * @param array<int, string> $pathPlaceholders
     * @param bool $useBody POST/PUT/PATCH 的字段默认放 body,其它放 query
     * @return string  path / query / body / header
     */
    private static function locationOf(string $name, array $pathPlaceholders, bool $useBody): string
    {
        if (in_array($name, [Header::clientKey(), Header::tokenKey()], true)) {
            return 'header';
        }
        if (in_array($name, $pathPlaceholders, true)) {
            return 'path';
        }
        return $useBody ? 'body' : 'query';
    }
}

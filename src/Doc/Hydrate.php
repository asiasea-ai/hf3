<?php

declare(strict_types=1);

namespace Hf3\Doc;

use Hf3\Doc\Util\DtoMap;
use Hf3\Doc\Util\Envelope;
use Hf3\Doc\Util\Modules;
use Hf3\Doc\Util\RouteCollect;
use Hf3\Doc\Util\Tagger;
use Hf3\Doc\Util\Type;
use Hf3\Doc\Util\VoMap;
use Hf3\Identity\Util\Header;
use Hf3\Middleware\Auth;
use Hf3\Middleware\Util\Ring;
use Hf3\Spl\SplDto;
use Hf3\Spl\SplVo;
use ReflectionMethod;

/**
 * 反射现有路由 / DTO / VO,产出完整 OpenAPI 3.0 spec.
 */
final class Hydrate
{
    /**
     * 生成 OpenAPI 3.0 spec(array 形式,caller 自己 json_encode)
     * @return array<string, mixed>
     */
    public static function spec(): array
    {
        $routes = RouteCollect::all('http');

        $paths = [];
        $schemas = Envelope::schemas();
        $ringLayerTags = [];

        foreach ($routes as $r) {
            /** Doc 自身两条路由不出现在文档里 */
            if (str_starts_with((string) $r['path'], '/__doc')) {
                continue;
            }
            $ring = Tagger::ringOfPath((string) $r['path']);
            $layer = Tagger::layerOfController((string) $r['controller']);
            $biz = Tagger::bizPathOfController((string) $r['controller']);
            $tagName = Tagger::tagNameOf($ring, $layer, $biz);

            $op = Hydrate::buildOperation($r, $schemas, $tagName);
            if ($op === null) {
                continue;
            }
            $oasPath = Hydrate::normalizePath($r['path']);
            $paths[$oasPath][strtolower($r['method'])] = $op;

            $label = Modules::labelOf($layer, $biz);
            $displayName = Tagger::tagDisplayOf($biz, $label);
            $ringLayerTags[$ring][$layer][$tagName] = $displayName;
        }

        [$tags, $tagGroups] = Tagger::buildTagGroups($ringLayerTags);

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Hf3 API',
                'version' => (string) ($_ENV['APP_ENV'] ?? 'dev'),
                'description' => '从 DTO/VO/Route 反射自动生成,业务代码零侵入.',
            ],
            'servers' => [
                ['url' => '/', 'description' => 'current'],
            ],
            'tags' => $tags,
            'x-tagGroups' => $tagGroups,
            'paths' => $paths,
            'components' => [
                'schemas' => $schemas,
                'securitySchemes' => [
                    'HfAppId' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => Header::clientKey(),
                    ],
                    'HfJwt' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => Header::tokenKey(),
                        'description' => 'Hf3 JWT token,登录后通过该 header 透传',
                    ],
                ],
            ],
        ];
    }

    /**
     * 单路由 → OpenAPI operation
     * @param array<string, mixed> $r
     * @param array<string, array<string, mixed>> &$schemas
     * @param string $tag op 归属的 tag 名(由 caller 调 Tagger 算好)
     * @return array<string, mixed>|null
     */
    private static function buildOperation(array $r, array &$schemas, string $tag): ?array
    {
        $ctrl = (string) $r['controller'];
        $action = (string) $r['action'];
        if (!class_exists($ctrl) || !method_exists($ctrl, $action)) {
            return null;
        }
        $rm = new ReflectionMethod($ctrl, $action);

        $dtoClass = Hydrate::findDtoParam($rm);
        $voClass  = Hydrate::findVoReturn($rm);

        [$summary, $description] = Hydrate::parsePhpDoc($rm->getDocComment() ?: '');

        $op = [
            'operationId' => Hydrate::operationId($ctrl, $action),
            'tags'        => [$tag],
            'summary'     => $summary,
        ];
        if ($description !== '') {
            $op['description'] = $description;
        }

        $parameters = [];
        $body = null;
        if ($dtoClass !== null) {
            $split = DtoMap::split($dtoClass, (string) $r['method'], (array) $r['placeholders']);
            $parameters = array_merge($split['path'], $split['query'], $split['header']);
            $body = $split['body'];
        } else {
            /** 没 DTO 也要把路由占位作为 path 参数声明出来 */
            foreach ((array) $r['placeholders'] as $name) {
                $parameters[] = [
                    'name' => $name,
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string'],
                ];
            }
        }
        if ($parameters !== []) {
            $op['parameters'] = $parameters;
        }
        if ($body !== null) {
            $op['requestBody'] = $body;
        }

        $auth = Hydrate::needsAuth($r['middlewares'], (string) $r['path']);

        $op['responses'] = Hydrate::buildResponses($voClass, $schemas, $auth);

        if ($auth) {
            $op['security'] = [['HfAppId' => [], 'HfJwt' => []]];
        }

        return $op;
    }

    /**
     * Controller 方法入参里找继承 SplDto 的那个
     * @param ReflectionMethod $rm
     * @return class-string<SplDto>|null
     */
    private static function findDtoParam(ReflectionMethod $rm): ?string
    {
        foreach ($rm->getParameters() as $p) {
            $fqcn = Type::classFqcn($p->getType());
            if ($fqcn !== null && is_subclass_of($fqcn, SplDto::class)) {
                return $fqcn;
            }
        }
        return null;
    }

    /**
     * Controller 方法返回类型 —— 继承 SplVo 的视为响应 VO,标量返回不画 data schema
     * @param ReflectionMethod $rm
     * @return class-string<SplVo>|null
     */
    private static function findVoReturn(ReflectionMethod $rm): ?string
    {
        $fqcn = Type::classFqcn($rm->getReturnType());
        if ($fqcn !== null && is_subclass_of($fqcn, SplVo::class)) {
            return $fqcn;
        }
        return null;
    }

    /**
     * 构造响应表 —— 与 Mapping::statusOf() 当前行为对齐:
     *   - 业务异常一律走 200 信封(code < Json::SUCCESS_MIN 即失败),所以默认只暴露 200
     *   - 需要鉴权的路由,鉴权失败抛 401,额外暴露一档 401
     * @param class-string<SplVo>|null $voClass
     * @param array<string, array<string, mixed>> &$schemas
     * @param bool $auth 路由是否需要鉴权(caller 用 needsAuth 算好)
     * @return array<string, mixed>
     */
    private static function buildResponses(?string $voClass, array &$schemas, bool $auth): array
    {
        $dataRef = $voClass === null
            ? ['nullable' => true]
            : VoMap::ref($voClass, $schemas);

        $responses = [
            '200' => Envelope::successResponse($dataRef),
        ];
        if ($auth) {
            $responses['401'] = Envelope::errorResponse('未登录 / Token 无效');
        }
        return $responses;
    }

    /**
     * 路由是否需要会话鉴权 —— 业务 ring 内且挂了 Auth 系中间件
     * 注:Auth 子类运行时只守自己的 ring(见 Auth::process),白名单路由也会放行,
     * 这里按"业务 ring + 存在 Auth 中间件"近似,文档可能对个别放行路由标多鉴权.
     * @param array<int, string> $middlewares
     * @param string $path
     * @return bool
     */
    private static function needsAuth(array $middlewares, string $path): bool
    {
        if (!Ring::isBusiness($path)) {
            return false;
        }
        foreach ($middlewares as $m) {
            if (is_subclass_of($m, Auth::class)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Hyperf 路由 `/{id:\d+}` → OpenAPI 标准 `/{id}`
     */
    private static function normalizePath(string $path): string
    {
        return (string) preg_replace('/\{([A-Za-z_][A-Za-z0-9_]*):[^}]+\}/u', '{$1}', $path);
    }

    /**
     * Controller PHPDoc 第一行 → summary,后续行 → description(过滤 @ 标签行)
     * @param string $doc
     * @return array{0:string, 1:string}
     */
    private static function parsePhpDoc(string $doc): array
    {
        if ($doc === '') {
            return ['', ''];
        }
        $stripped = (string) preg_replace('#^/\*+\s*|\s*\*+/$#u', '', $doc);
        $lines = preg_split('/\R/u', $stripped) ?: [];
        $cleaned = [];
        foreach ($lines as $l) {
            $t = trim($l, " \t*");
            if ($t === '' || str_starts_with($t, '@')) {
                continue;
            }
            /** 跳过路由声明行 "GET /path" "POST /path" 等(约定写在 Controller PHPDoc 里给开发者看) */
            if (preg_match('/^(GET|POST|PUT|DELETE|PATCH|HEAD|OPTIONS)\s+\//u', $t)) {
                continue;
            }
            $cleaned[] = $t;
        }
        if ($cleaned === []) {
            return ['', ''];
        }
        $summary = array_shift($cleaned);
        return [$summary, implode("\n", $cleaned)];
    }

    /**
     * operationId —— 用 Controller FQCN 尾段 + action,如 Account.listing
     * @param string $ctrl
     * @param string $action
     * @return string
     */
    private static function operationId(string $ctrl, string $action): string
    {
        $parts = explode('\\', $ctrl);
        $last = array_pop($parts);
        return $last . '.' . $action;
    }

}

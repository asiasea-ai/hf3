<?php

declare(strict_types=1);

namespace Hf3\Middleware;

use Hf3\Dto\Hydrator;
use Hf3\Identity\Util\Header;
use Hf3\Vo\Json;
use Hf3\Vo\Renderer;
use Hyperf\Contract\Arrayable;
use Hyperf\Contract\Jsonable;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\CoreMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;
use ReflectionNamedType;
use Swow\Psr7\Message\ResponsePlusInterface;

/**
 * Hyperf CoreMiddleware 的 Hf3 扩展 —— DTO 自动注入 + 业务 envelope 包装.
 */
final class Hf3Core extends CoreMiddleware
{
    protected function parseMethodParameters(string $controller, string $action, array $arguments): array
    {
        $arguments = $this->hydrateDtoArguments($controller, $action, $arguments);
        return parent::parseMethodParameters($controller, $action, $arguments);
    }

    protected function transferToResponse($response, ServerRequestInterface $request): ResponsePlusInterface
    {
        if ($response instanceof ResponseInterface) {
            return parent::transferToResponse($response, $request);
        }

        if ($response === null) {
            $wrapped = Json::ok(null)->toArray();
            return $this->response()
                ->addHeader('content-type', 'application/json')
                ->setBody(new SwooleStream(json_encode($wrapped, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
        }

        $data = match (true) {
            is_object($response) && method_exists($response, 'toArray') => $response->toArray(),
            $response instanceof Arrayable                         => $response->toArray(),
            $response instanceof Jsonable                          => json_decode((string) $response, true),
            is_object($response)                                   => Renderer::toArray($response),
            default                                                => $response,
        };

        $wrapped = Json::ok($data)->toArray();
        return $this->response()
            ->addHeader('content-type', 'application/json')
            ->setBody(new SwooleStream(json_encode($wrapped, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
    }

    private function hydrateDtoArguments(string $controller, string $action, array $arguments): array
    {
        try {
            $rm = new ReflectionMethod($controller, $action);
        } catch (\Throwable) {
            return $arguments;
        }

        $bodyMerged = null;

        foreach ($rm->getParameters() as $i => $p) {
            $type = $p->getType();
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $fqcn = $type->getName();
            if (!str_contains($fqcn, '\\Dto\\')) {
                continue;
            }

            $bodyMerged ??= array_merge(
                $this->normalizePathParams($arguments),
                $this->collectRequestBody(),
            );
            $dto = Hydrator::hydrate($fqcn, $bodyMerged);

            $arguments[$i] = $dto;
            $arguments[$p->getName()] = $dto;
        }

        return $arguments;
    }

    private function normalizePathParams(array $arguments): array
    {
        $out = [];
        foreach ($arguments as $k => $v) {
            if (is_string($k)) {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    private function collectRequestBody(): array
    {
        /** @var RequestInterface $request */
        $request = $this->container->get(RequestInterface::class);
        $query   = (array) $request->getQueryParams();
        $parsed  = (array) $request->getParsedBody();

        $headers = [];
        foreach ([Header::clientKey(), Header::tokenKey()] as $headerKey) {
            $v = $request->getHeaderLine($headerKey);
            if ($v !== '') {
                $headers[$headerKey] = $v;
            }
        }
        return array_merge($headers, $query, $parsed);
    }
}

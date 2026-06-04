<?php

declare(strict_types=1);

namespace Hf3\Doc;

use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\ResponseInterface as HyperfResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * Doc 端点 —— /__doc Swagger UI / /__doc/openapi.json spec.
 */
final class Controller
{
    /**
     * 输出 OpenAPI 3.0 JSON spec
     * GET /__doc/openapi.json
     * @param HyperfResponse $response
     * @return ResponseInterface
     */
    public function spec(HyperfResponse $response): ResponseInterface
    {
        $spec = Hydrate::spec();
        $json = (string) json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody(new SwooleStream($json));
    }

    /**
     * 内嵌 Scalar API Reference(CDN 引 @scalar/api-reference)
     * GET /__doc
     * @param HyperfResponse $response
     * @return ResponseInterface
     */
    public function ui(HyperfResponse $response): ResponseInterface
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hf3 API Docs</title>
    <style>html,body{margin:0}</style>
</head>
<body>
<script
    id="api-reference"
    data-url="/__doc/openapi.json"
    data-configuration='{"theme":"default","layout":"modern","hideModels":true,"hideDownloadButton":false,"showSidebar":true,"defaultOpenAllTags":false}'
></script>
<script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
</body>
</html>
HTML;

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody(new SwooleStream($html));
    }
}

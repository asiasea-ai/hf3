<?php

declare(strict_types=1);

namespace Hf3\Throwable\Trigger;

use Hf3\Throwable\Trigger as HfTrigger;
use Hf3\Throwable\Util\Mapping;
use Hf3\Vo\Json;
use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * 全局 HTTP 异常 → JSON envelope 转换 trigger.
 */
final class Http
{
    /**
     * 处理 HTTP 异常 —— 上报 HfTrigger,出 Hf3 业务码 + 友好 msg + 正确 HTTP 状态码
     * @param Throwable $throwable
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public static function run(
        Throwable $throwable,
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        /** 从容器拿 HfTrigger 实例,拿不到回退 new 一个 —— 拆变量符合 CLAUDE.md "嵌套 ≥ 2 层拆变量" */
        $container = ApplicationContext::getContainer();
        $hasService = $container instanceof ContainerInterface && $container->has(HfTrigger::class);
        $service = $hasService ? $container->get(HfTrigger::class) : null;
        $trigger = $service instanceof HfTrigger ? $service : new HfTrigger();
        $trigger->throwable($throwable);

        [$code, $msg] = Mapping::codeAndMessage($throwable);
        $body = Json::fail(code: $code, msg: $msg)->toJson();
        $status = Mapping::statusOf($throwable);

        $response = $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody(new SwooleStream($body));

        Context::set(ResponseInterface::class, $response);
        Context::set('es37_response_written', true);

        return $response;
    }
}

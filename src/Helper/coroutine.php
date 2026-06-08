<?php

declare(strict_types=1);

use Hf3\Code\Code;
use Hf3\Throwable\Exception\ErrorException;
use Hyperf\Context\ApplicationContext;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Coroutine\WaitGroup;
use Hyperf\Logger\LoggerFactory;

/**
 * 安全地起一个协程执行 callable, 内部捕获异常并写 logger, 不让异常逃逸到调度器
 * @param callable $callable 要在协程中执行的逻辑
 * @param mixed ...$args 透传给 callable 的参数
 * @return int 新协程的 ID
 */
function easyGo(callable $callable, mixed ...$args): int
{
    return Coroutine::fork(static function () use ($callable, $args): void {
        try {
            $callable(...$args);
        } catch (\Throwable $e) {
            _hf3EasyGoLogError($e);
        }
    });
}

/**
 * 并发执行一组任务并按原 key 收集结果, 全部完成或超时后返回
 * @param array $tasks key => callable 的任务表
 * @param float $timeout 等待全部完成的超时秒数
 * @return array 与入参同 key 的结果表; 超时抛 ErrorException, 有任务异常则逐条写 logger 后抛首个异常
 */
function waitGroup(array $tasks, float $timeout = 3.0): array
{
    if ($tasks === []) {
        return [];
    }

    $results = array_fill_keys(array_keys($tasks), null);
    $errors  = [];
    $wg      = new WaitGroup();
    $wg->add(count($tasks));

    foreach ($tasks as $key => $task) {
        Coroutine::fork(static function () use ($task, $key, $wg, &$results, &$errors): void {
            try {
                $results[$key] = $task();
            } catch (\Throwable $e) {
                $errors[$key] = $e;
            } finally {
                $wg->done();
            }
        });
    }

    if ($wg->wait($timeout) === false) {
        throw new ErrorException(
            code: Code::COROUTINE_TIMEOUT,
            message: sprintf('waitGroup timeout after %.2fs (%d tasks pending)', $timeout, count($tasks)),
            category: 'coroutine',
        );
    }

    if ($errors !== []) {
        // 老版本只抛 $errors[0] 把其它错误吞掉, 这里把每条 error 单独写 logger,
        // 排查时按 'easy-go' channel 能拿到全部. 类型上仍抛 first error 保 caller catch 不变.
        foreach ($errors as $key => $err) {
            _hf3EasyGoLogError($err, "waitGroup task[$key]");
        }
        throw reset($errors);
    }

    return $results;
}

/**
 * 内部 helper —— 拿到 Hyperf logger 写 error.
 * 容器拿不到 (单测 / 启动早期) 直接吞,不让本辅助二次抛.
 */
function _hf3EasyGoLogError(\Throwable $e, string $prefix = 'easyGo'): void
{
    try {
        $logger = ApplicationContext::getContainer()->get(LoggerFactory::class)->get('easy-go', 'default');
        $logger->error(sprintf('%s: %s @%s:%d', $prefix, $e->getMessage(), $e->getFile(), $e->getLine()));
    } catch (\Throwable) {
        // 容器未就绪;吞.
    }
}

<?php

declare(strict_types=1);

use Hf3\Code\Code;
use Hf3\Throwable\Exception\ErrorException;
use Hyperf\Context\ApplicationContext;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Coroutine\WaitGroup;
use Hyperf\Logger\LoggerFactory;

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

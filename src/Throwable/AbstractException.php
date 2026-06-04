<?php

declare(strict_types=1);

namespace Hf3\Throwable;

use Hf3\Code\CodeInterface;
use Hf3\Throwable\Enum\Level;

abstract class AbstractException extends \Exception
{
    /**
     * 异常构造
     * @param CodeInterface $code
     * @param string|null $message
     * @param string|null $category
     * @param \Throwable|null $previous
     * @param int|null $httpStatus
     */
    public function __construct(
        CodeInterface $code,
        ?string $message = null,
        public readonly ?string $category = null,
        ?\Throwable $previous = null,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message ?? $code->message(), (int) $code->value, $previous);
    }

    /**
     * 获取日志级别
     * @return Level
     */
    abstract public function level(): Level;
}

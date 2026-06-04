<?php

declare(strict_types=1);

namespace Hf3\Listener\Db;

use Doctrine\SqlFormatter\CliHighlighter;
use Doctrine\SqlFormatter\NullHighlighter;
use Doctrine\SqlFormatter\SqlFormatter;
use Hyperf\Database\Events\QueryExecuted;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use function Hyperf\Config\config;

/**
 * SQL 调试打印 listener —— 仅用于开发期 stdout 输出, 格式化交给 doctrine/sql-formatter.
 * 生产环境 (APP_ENV=production) 直接 return.
 */
#[Listener]
final readonly class PrintQuery implements ListenerInterface
{
    private SqlFormatter $formatter;

    public function __construct()
    {
        $highlighter = (function_exists('posix_isatty') && posix_isatty(STDOUT))
            ? new CliHighlighter()
            : new NullHighlighter();
        $this->formatter = new SqlFormatter($highlighter);
    }

    public function listen(): array
    {
        return [QueryExecuted::class];
    }

    public function process(object $event): void
    {
        assert($event instanceof QueryExecuted);

        if (isProduction()) {
            return;
        }

        $formatted = $this->formatter->format($event->sql);
        $bindings = json_encode((array) $event->bindings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        'Y-m-d H:i:s'
            |> date(...)
            |> (fn($x) => sprintf("\n[%s] [SQL] [pool=%s] [%.2fms]\n%s\nbindings: %s\n\n", $x, $event->connectionName ?: 'default', (float)$event->time, $formatted, $bindings,))
            |> (fn($x) => fwrite(STDOUT, $x));
    }
}

<?php

declare(strict_types=1);

namespace Hf3\Listener;

use Hf3\Util\BootLog;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeMainServerStart;
use Hyperf\HttpServer\Router\DispatcherFactory;

/**
 * 主 server 启动前打印环境信息卡: Swoole / MySQL / Redis / Route / Crontab / Processes.
 */
#[Listener]
class StartupBanner implements ListenerInterface
{
    public function __construct(private ConfigInterface $config)
    {
    }

    public function listen(): array
    {
        return [BeforeMainServerStart::class];
    }

    public function process(object $event): void
    {
        $this->sectionSwooleServer();
        $this->sectionRuntimeFolders();
        $this->sectionMysqlPool();
        $this->sectionRedisPool();
        $this->sectionRouteLoader();
        $this->sectionCrontabJobs();
        $this->sectionCustomProcesses();

        BootLog::flush();
    }

    private function sectionSwooleServer(): void
    {
        $servers  = (array) $this->config->get('server.servers', []);
        $http     = $servers[0] ?? [];
        $settings = (array) $this->config->get('server.settings', []);

        BootLog::section('Swoole Server', [
            ['name',        (string) ($http['name'] ?? 'http')],
            ['listen',      ($http['host'] ?? '0.0.0.0') . ':' . ($http['port'] ?? '?')],
            ['worker_num',  (string) ($settings['worker_num'] ?? swoole_cpu_num())],
            ['max_request', (string) ($settings['max_request'] ?? 0)],
            ['pid_file',    (string) ($settings['pid_file'] ?? '')],
            ['mode',        $this->modeName((int) $this->config->get('server.mode', SWOOLE_PROCESS))],
            ['coroutine',   ($settings['enable_coroutine'] ?? false) ? 'on' : 'off'],
        ]);
    }

    private function sectionRuntimeFolders(): void
    {
        BootLog::section('Runtime Folders', [
            ['base_path', BASE_PATH],
            ['runtime',   RUNTIME_PATH],
            ['pid',       RUNTIME_PATH . '/pid'],
            ['log',       RUNTIME_PATH . '/logs'],
            ['container', RUNTIME_PATH . '/container'],
        ]);
    }

    private function sectionMysqlPool(): void
    {
        $databases = (array) $this->config->get('databases', []);
        $rows      = [];
        $maxObj    = null;
        $minObj    = null;

        foreach ($databases as $name => $conf) {
            if (!is_array($conf) || $name === 'default') {
                continue;
            }
            $rows[] = [(string) $name, sprintf(
                '%s@%s:%d/%s',
                $conf['username'] ?? '',
                $conf['host'] ?? '',
                (int) ($conf['port'] ?? 0),
                $conf['database'] ?? '',
            )];
            $maxObj ??= (int) ($conf['pool']['max_connections'] ?? 0);
            $minObj ??= (int) ($conf['pool']['min_connections'] ?? 0);
        }
        if ($rows !== [] && $maxObj !== null) {
            $rows[] = sprintf('pool: max=%d, min=%d (per worker)', $maxObj, $minObj);
        }
        BootLog::section('MySQL Pool (Hyperf)', $rows);
    }

    private function sectionRedisPool(): void
    {
        $pools  = (array) $this->config->get('redis', []);
        $rows   = [];
        $maxObj = null;
        $minObj = null;

        foreach ($pools as $name => $conf) {
            if (!is_array($conf)) {
                continue;
            }
            $rows[] = [(string) $name, sprintf(
                '%s:%d/db=%d',
                $conf['host'] ?? '',
                (int) ($conf['port'] ?? 0),
                (int) ($conf['db'] ?? 0),
            )];
            $maxObj ??= (int) ($conf['pool']['max_connections'] ?? 0);
            $minObj ??= (int) ($conf['pool']['min_connections'] ?? 0);
        }
        if ($rows !== [] && $maxObj !== null) {
            $rows[] = sprintf('pool: max=%d, min=%d (per worker)', $maxObj, $minObj);
        }
        BootLog::section('Redis Pool (Hyperf)', $rows);
    }

    private function sectionRouteLoader(): void
    {
        try {
            $factory = ApplicationContext::getContainer()->get(DispatcherFactory::class);
            $data    = $factory->getRouter('http')->getData();

            $count   = 0;
            $methods = [];
            foreach ($data[0] ?? [] as $method => $routes) {
                $methods[$method] = ($methods[$method] ?? 0) + count($routes);
                $count += count($routes);
            }
            foreach ($data[1] ?? [] as $method => $chunks) {
                foreach ($chunks as $chunk) {
                    $n = count($chunk['routeMap'] ?? []);
                    $methods[$method] = ($methods[$method] ?? 0) + $n;
                    $count += $n;
                }
            }

            $rows = [];
            ksort($methods);
            foreach ($methods as $m => $n) {
                $rows[] = [$m, (string) $n];
            }
            $rows[] = ['total', (string) $count];
            BootLog::section('Route Loader', $rows);
        } catch (\Throwable $e) {
            BootLog::section('Route Loader', [['error', BootLog::red($e->getMessage())]]);
        }
    }

    private function sectionCrontabJobs(): void
    {
        $jobs = (array) $this->config->get('crontab.crontab', []);
        $rows = [];
        foreach ($jobs as $job) {
            if (is_array($job)) {
                $rows[] = [(string) ($job['name'] ?? $job['callback'] ?? 'anonymous'), (string) ($job['rule'] ?? '')];
            } elseif (is_object($job) && method_exists($job, 'getName') && method_exists($job, 'getRule')) {
                $rows[] = [(string) $job->getName(), (string) $job->getRule()];
            }
        }
        if ($rows === []) {
            $rows[] = ['(none)', 'add to config/autoload/crontab.php'];
        }
        $rows[] = ['total', (string) (count($rows) - ($jobs === [] ? 1 : 0))];
        BootLog::section('Crontab Jobs', $rows);
    }

    private function sectionCustomProcesses(): void
    {
        $processes = (array) $this->config->get('processes', []);
        $rows = [];
        foreach ($processes as $p) {
            $rows[] = is_string($p) ? $p : (is_object($p) ? $p::class : 'unknown');
        }
        if ($rows === []) {
            $rows[] = '(none)';
        }
        $rows[] = 'total: ' . (string) count($processes);
        BootLog::section('Custom Processes', $rows);
    }

    private function modeName(int $mode): string
    {
        return match ($mode) {
            SWOOLE_PROCESS => 'SWOOLE_PROCESS',
            SWOOLE_BASE    => 'SWOOLE_BASE',
            default        => 'UNKNOWN(' . $mode . ')',
        };
    }
}

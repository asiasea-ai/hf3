<?php

declare(strict_types=1);

namespace Hf3\Listener;

use Composer\InstalledVersions;
use Hf3\Util\BootLog;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Crontab\Annotation\Crontab as CrontabAnnotation;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeMainServerStart;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\Process\Annotation\Process as ProcessAnnotation;

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
        $this->sectionRuntimeFolders();
        BootLog::sectionPair('Route Loader', $this->routeRows(), 'Workers', $this->workerRows());
        BootLog::sectionPair('MySQL Pool (Hyperf)', $this->mysqlRows(), 'Redis Pool (Hyperf)', $this->redisRows());
        BootLog::sectionPair('Crontab Jobs', $this->crontabRows(), 'Custom Processes', $this->processRows());
        BootLog::sectionPair('Server', $this->serverRows(), 'Swoole Server', $this->swooleRows());

        BootLog::flush();
    }

    /**
     * Server 概览行 —— 值由 Boot 监听器(BootApplication)生成并存入容器,这里只读取展示
     * @return array<int, array{0: string, 1: string}>
     */
    private function serverRows(): array
    {
        $container = ApplicationContext::getContainer();
        $read = static function (string $key) use ($container): string {
            return $container->has($key) ? (string) $container->get($key) : 'unknown';
        };
        $hyperf = InstalledVersions::getPrettyVersion('hyperf/framework') ?? 'unknown';

        return [
            ['hostname',    $read('server.hostname')],
            ['ip',          $read('server.ip')],
            ['instance_id', $read('server.instance_id')],
            ['run_time',    $read('server.run_time')],
            ['php',         PHP_VERSION],
            ['swoole',      SWOOLE_VERSION],
            ['hyperf',      $hyperf],
            ['app_env',     (string) ($_ENV['APP_ENV'] ?? 'dev')],
        ];
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function swooleRows(): array
    {
        $servers  = (array) $this->config->get('server.servers', []);
        $http     = $servers[0] ?? [];
        $settings = (array) $this->config->get('server.settings', []);

        return [
            ['name',        (string) ($http['name'] ?? 'http')],
            ['listen',      ($http['host'] ?? '0.0.0.0') . ':' . ($http['port'] ?? '?')],
            ['worker_num',  (string) ($settings['worker_num'] ?? swoole_cpu_num())],
            ['max_request', (string) ($settings['max_request'] ?? 0)],
            ['mode',        $this->modeName((int) $this->config->get('server.mode', SWOOLE_PROCESS))],
            ['coroutine',   ($settings['enable_coroutine'] ?? false) ? 'on' : 'off'],
        ];
    }

    /**
     * @return array<int, array{0: string, 1: string}|string>
     */
    private function workerRows(): array
    {
        $settings  = (array) $this->config->get('server.settings', []);
        $workerNum = (int) ($settings['worker_num'] ?? swoole_cpu_num());

        /** 只展示前几个,其余折叠成一行,末尾给总数 */
        $preview  = 3;
        $showCount = min($preview, $workerNum);

        $rows = [];
        for ($i = 0; $i < $showCount; $i++) {
            $rows[] = ['Worker#' . $i, 'started'];
        }
        $rest = $workerNum - $showCount;
        if ($rest > 0) {
            $rows[] = ['...', '+' . $rest . ' more'];
        }
        $rows[] = ['total', (string) $workerNum];
        return $rows;
    }

    private function sectionRuntimeFolders(): void
    {
        $settings = (array) $this->config->get('server.settings', []);
        $pidFile  = (string) ($settings['pid_file'] ?? RUNTIME_PATH . '/pid/hyperf.pid');

        BootLog::section('Runtime Folders', [
            ['base_path', BASE_PATH],
            ['runtime',   RUNTIME_PATH],
            ['pid',       RUNTIME_PATH . '/pid'],
            ['pid_file',  $pidFile],
            ['log',       RUNTIME_PATH . '/logs'],
            ['container', RUNTIME_PATH . '/container'],
        ]);
    }

    /**
     * @return array<int, array{0: string, 1: string}|string>
     */
    private function mysqlRows(): array
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
            $rows[] = sprintf('pool: max=%d, min=%d', $maxObj, $minObj);
        }
        return $rows;
    }

    /**
     * @return array<int, array{0: string, 1: string}|string>
     */
    private function redisRows(): array
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
            $rows[] = sprintf('pool: max=%d, min=%d', $maxObj, $minObj);
        }
        return $rows;
    }

    /**
     * @return array<int, array{0: string, 1: string}|string>
     */
    private function routeRows(): array
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
            return $rows;
        } catch (\Throwable $e) {
            return [['error', BootLog::red($e->getMessage())]];
        }
    }

    /**
     * @return array<int, array{0: string, 1: string}|string>
     */
    private function crontabRows(): array
    {
        $configJobs = (array) $this->config->get('crontab.crontab', []);
        $classJobs  = AnnotationCollector::getClassesByAnnotation(CrontabAnnotation::class);
        $methodJobs = AnnotationCollector::getMethodsByAnnotation(CrontabAnnotation::class);

        /** 按任务名去重(配置式 + 类级注解 + 方法级注解) */
        $jobs = [];
        foreach ($configJobs as $job) {
            if (is_array($job)) {
                $name = (string) ($job['name'] ?? $job['callback'] ?? 'anonymous');
                $rule = (string) ($job['rule'] ?? '');
                $jobs[$name] = [$name, $rule];
            } elseif (is_object($job) && method_exists($job, 'getName') && method_exists($job, 'getRule')) {
                $name = (string) $job->getName();
                $rule = (string) $job->getRule();
                $jobs[$name] = [$name, $rule];
            }
        }
        foreach ($classJobs as $annotation) {
            if ($annotation instanceof CrontabAnnotation) {
                $name = (string) ($annotation->name ?? 'anonymous');
                $rule = (string) ($annotation->rule ?? '');
                $jobs[$name] = [$name, $rule];
            }
        }
        foreach ($methodJobs as $item) {
            $annotation = $item['annotation'] ?? null;
            if ($annotation instanceof CrontabAnnotation) {
                $name = (string) ($annotation->name ?? 'anonymous');
                $rule = (string) ($annotation->rule ?? '');
                $jobs[$name] = [$name, $rule];
            }
        }

        if ($jobs === []) {
            return [['(none)', 'no #[Crontab] / config']];
        }

        $rows = array_values($jobs);
        $rows[] = ['total', (string) count($jobs)];
        return $rows;
    }

    /**
     * @return array<int, array{0: string, 1: string}|string>
     */
    private function processRows(): array
    {
        $configProcesses     = (array) $this->config->get('processes', []);
        $annotationProcesses = AnnotationCollector::getClassesByAnnotation(ProcessAnnotation::class);

        /** 按类名去重(配置式 + #[Process] 注解式),只展示短名,不显示完整类路径 */
        $procs = [];
        foreach ($configProcesses as $p) {
            $class = is_string($p) ? $p : (is_object($p) ? $p::class : 'unknown');
            $short = $this->classShortName($class);
            $procs[$class] = [$short, 'config'];
        }
        foreach ($annotationProcesses as $class => $annotation) {
            $name = $annotation instanceof ProcessAnnotation ? (string) ($annotation->name ?? '') : '';
            $short = $name !== '' ? $name : $this->classShortName($class);
            $procs[$class] = [$short, '#[Process]'];
        }

        if ($procs === []) {
            return [['(none)', '']];
        }

        $rows = array_values($procs);
        $rows[] = ['total', (string) count($procs)];
        return $rows;
    }

    private function classShortName(string $class): string
    {
        $pos = strrpos($class, '\\');
        return $pos === false ? $class : substr($class, $pos + 1);
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

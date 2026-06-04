<?php

declare(strict_types=1);

namespace Hf3\Listener;

use Hf3\Util\BootLog;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Psr\Container\ContainerInterface;

#[Listener]
readonly class Boot implements ListenerInterface
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function listen(): array
    {
        return [BootApplication::class];
    }

    public function process(object $event): void
    {
        $instanceId = bin2hex(random_bytes(16));
        $runTime    = date('Y-m-d H:i:s');
        $hostname   = gethostname() ?: 'unknown';
        $ip         = gethostbyname($hostname);
        $ip         = ($ip !== $hostname) ? $ip : '0.0.0.0';

        $this->container->set('server.instance_id', $instanceId);
        $this->container->set('server.run_time', $runTime);
        $this->container->set('server.hostname', $hostname);
        $this->container->set('server.ip', $ip);

        BootLog::section('Server', [
            ['hostname',    $hostname],
            ['ip',          $ip],
            ['instance_id', $instanceId],
            ['run_time',    $runTime],
            ['php',         PHP_VERSION],
            ['swoole',      SWOOLE_VERSION],
            ['hyperf',      \Composer\InstalledVersions::getPrettyVersion('hyperf/framework') ?? 'unknown'],
            ['base_path',   BASE_PATH],
            ['app_env',     (string) ($_ENV['APP_ENV'] ?? 'dev')],
        ]);
    }
}

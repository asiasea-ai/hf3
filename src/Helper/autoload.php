<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/*.php') as $file) {
    if ($file === __FILE__) {
        continue;
    }
    require_once $file;
}

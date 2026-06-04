<?php

declare(strict_types=1);

namespace Hf3\Whitelist;

use Closure;
use Hf3\Whitelist\Util\Matcher;

/**
 * JWT 白名单匹配器 —— 从 etc 加载规则,启动时预编译匹配函数.
 */
final readonly class Whitelist
{
    /** @var list<array{method: string, path: string, matcher: Closure|null}> */
    private array $rules;

    public function __construct()
    {
        $groups = (array) etc('oidc.whitelist', []);
        $rules  = [];
        foreach ($groups as $entries) {
            if (!is_array($entries)) {
                continue;
            }
            foreach ($entries as $entry) {
                if (!is_array($entry) || count($entry) !== 2) {
                    continue;
                }
                [$method, $path] = $entry;
                $rules[] = [
                    'method'  => strtoupper((string) $method),
                    'path'    => (string) $path,
                    'matcher' => Matcher::compilePathMatcher((string) $path),
                ];
            }
        }
        $this->rules = $rules;
    }

    /**
     * 命中任一规则即返 true
     *
     * @param string $method HTTP method,如 'GET' / 'POST'(大小写不敏感)
     * @param string $path   URL path,不含 query string
     * @return bool
     */
    public function match(string $method, string $path): bool
    {
        $upMethod = strtoupper($method);
        foreach ($this->rules as $r) {
            if (!Matcher::methodHit($r['method'], $upMethod)) {
                continue;
            }
            if ($r['matcher'] === null) {
                if ($r['path'] === $path) {
                    return true;
                }
                continue;
            }
            if (($r['matcher'])($path)) {
                return true;
            }
        }
        return false;
    }
}

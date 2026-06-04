<?php

declare(strict_types=1);

namespace Hf3\Enum;

use Hf3\Enum\Util\Scan;

/**
 * 聚合 App/Module/<L1>/<L2>/Constant/Enum.php 的全量枚举字典,首访扫盘+反射,二访进程内 O(1).
 * 对外只暴露 alias,table.column → alias 映射通过 aliases() / lookup() 反查.
 */
final class Auto
{
    /** @var array<string, array<int|string, string>>|null alias → KEY → label,/web/bi/enum 暴露给前端 */
    private static ?array $allCache = null;

    /** @var array<string, string>|null table.column → alias,代码内业务反查 */
    private static ?array $aliasCache = null;

    /** @var string|null all() 字典的 sha256 hex,版本协商用 */
    private static ?string $versionCache = null;

    /**
     * 聚合所有子模块的枚举字典 —— alias → KEY → label
     * @return array<string, array<int|string, string>>
     */
    public static function all(): array
    {
        if (Auto::$allCache !== null) {
            return Auto::$allCache;
        }

        $root = BASE_PATH . '/App/Module';
        $classes = Scan::moduleEnumClasses($root);

        $dict = [];
        foreach ($classes as $className) {
            $entries = Scan::tryAll($className);
            $dict = $dict + $entries;
        }

        Auto::$allCache = $dict;
        return $dict;
    }

    /**
     * 聚合所有子模块的 table.column → alias 反查映射
     * @return array<string, string>
     */
    public static function aliases(): array
    {
        if (Auto::$aliasCache !== null) {
            return Auto::$aliasCache;
        }

        $root = BASE_PATH . '/App/Module';
        $classes = Scan::moduleEnumClasses($root);

        $map = [];
        foreach ($classes as $className) {
            $entries = Scan::tryAliases($className);
            $map = $map + $entries;
        }

        Auto::$aliasCache = $map;
        return $map;
    }

    /**
     * 通过 table.column 直接查 KEY → label 字典 —— 业务侧不必关心 alias
     * 不存在返 []
     * @return array<int|string, string>
     */
    public static function lookup(string $tableColumn): array
    {
        $alias = Auto::aliases()[$tableColumn] ?? null;
        if ($alias === null) {
            return [];
        }
        return Auto::all()[$alias] ?? [];
    }

    /**
     * 获取 all() 字典的 sha256 版本号 —— 命中协商时省去再次 json_encode
     * @return string 64 位 sha256 hex
     */
    public static function version(): string
    {
        if (Auto::$versionCache !== null) {
            return Auto::$versionCache;
        }

        $dict = Auto::all();
        $payload = json_encode($dict, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $payload);

        Auto::$versionCache = $hash;
        return $hash;
    }

    /**
     * 清空 cache —— 测试 / 热更场景重新扫描
     */
    public static function flush(): void
    {
        Auto::$allCache = null;
        Auto::$aliasCache = null;
        Auto::$versionCache = null;
    }
}

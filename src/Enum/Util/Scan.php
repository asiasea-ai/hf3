<?php

declare(strict_types=1);

namespace Hf3\Enum\Util;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * 模块枚举扫描器 —— Constant/Enum.php 路径发现 + FQCN 推导 + ::all() 安全调用.
 */
final class Scan
{
    /**
     * 扫描指定 App/Module 根,返回所有 Constant/Enum FQCN 列表
     * @return list<string>
     */
    public static function moduleEnumClasses(string $moduleRoot): array
    {
        if (!is_dir($moduleRoot)) {
            return [];
        }

        $paths = Scan::collectEnumFiles($moduleRoot);

        $classes = [];
        foreach ($paths as $path) {
            $fqcn = Scan::deriveClass($path);
            if ($fqcn !== null) {
                $classes[] = $fqcn;
            }
        }
        return $classes;
    }

    /**
     * 收集 <root>/<L1>/<L2>/Constant/Enum.php 绝对路径
     * @return list<string>
     */
    public static function collectEnumFiles(string $moduleRoot): array
    {
        if (!is_dir($moduleRoot)) {
            return [];
        }

        $flags = FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS;
        $dir = new RecursiveDirectoryIterator($moduleRoot, $flags);
        $iter = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::LEAVES_ONLY);

        $files = [];
        foreach ($iter as $file) {
            if ($file->getFilename() !== 'Enum.php') {
                continue;
            }
            $path = $file->getPathname();
            $parentDir = basename(dirname($path));
            if ($parentDir !== 'Constant') {
                continue;
            }
            $files[] = $path;
        }
        return $files;
    }

    /**
     * 绝对路径 → FQCN —— App/Module/.../Constant/Enum.php → App\Module\...\Constant\Enum
     * 路径不含 /App/Module/ 段直接返 null
     */
    public static function deriveClass(string $absolutePath): ?string
    {
        $marker = '/App/Module/';
        $pos = strpos($absolutePath, $marker);
        if ($pos === false) {
            return null;
        }

        $relative = substr($absolutePath, $pos + 1);
        $stripped = preg_replace('/\.php$/', '', $relative);
        if ($stripped === null || $stripped === '') {
            return null;
        }
        return str_replace('/', '\\', $stripped);
    }

    /**
     * 调 className::all(),容错:类/方法缺失/返回非数组 → 空数组
     * @return array<string, array<int|string, string>>
     */
    public static function tryAll(string $className): array
    {
        return Scan::callArrayStatic($className, 'all');
    }

    /**
     * 调 className::aliases(),容错:类/方法缺失/返回非数组 → 空数组
     * @return array<string, string>
     */
    public static function tryAliases(string $className): array
    {
        return Scan::callArrayStatic($className, 'aliases');
    }

    /**
     * 反射调 $class::$method(),要求无参 + 返数组,任一不满足返 []
     * @return array<mixed>
     */
    public static function callArrayStatic(string $className, string $method): array
    {
        if (!class_exists($className)) {
            return [];
        }
        if (!method_exists($className, $method)) {
            return [];
        }
        $result = $className::$method();
        if (!is_array($result)) {
            return [];
        }
        return $result;
    }
}

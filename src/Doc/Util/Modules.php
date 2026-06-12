<?php

declare(strict_types=1);

namespace Hf3\Doc\Util;

/**
 * OpenAPI tag 中文名加载 —— 读 Doc/api/modules.php(文档期配置),模块路径 → 中文备注.
 */
final class Modules
{
    /** worker 进程内缓存,null 表示尚未加载 */
    private static ?array $cache = null;

    /**
     * 取模块中文名 —— layer + biz 拼成 `{layer}\{biz...}` 查表,biz 空或缺失返 null
     * @param string $layer
     * @param array<int, string> $biz
     * @return string|null
     */
    public static function labelOf(string $layer, array $biz): ?string
    {
        if ($biz === []) {
            return null;
        }

        $segs = array_merge([$layer], $biz);
        $key = implode('\\', $segs);

        $labels = Modules::labels();
        $label = $labels[$key] ?? null;
        return is_string($label) && $label !== '' ? $label : null;
    }

    /**
     * 加载并缓存 Doc/api/modules.php —— 文件缺失或内容非数组时返空表
     * @return array<string, string>
     */
    public static function labels(): array
    {
        if (Modules::$cache !== null) {
            return Modules::$cache;
        }

        $file = BASE_PATH . '/Doc/api/modules.php';
        $labels = is_file($file) ? require $file : [];
        Modules::$cache = is_array($labels) ? $labels : [];
        return Modules::$cache;
    }
}

<?php

declare(strict_types=1);

namespace Hf3\Doc\Util;

/**
 * OpenAPI tag / x-tagGroups 解析 —— 路径 → ring、Controller FQCN → layer + 业务层级.
 */
final class Tagger
{
    private const RINGS = ['web', 'app', 'rpc'];

    private const LAYERS = ['Lib', 'Module', 'Demo'];

    private const RING_LABEL = [
        'web' => 'Web 管理后台',
        'app' => 'App 客户端',
        'rpc' => 'Rpc 内部调用',
        'root' => '顶级',
    ];

    private const RING_ORDER = ['web', 'app', 'rpc', 'root'];

    private const LAYER_ORDER = ['Module', 'Lib', 'Demo'];

    /**
     * 从路径首段解析 ring;不在 web/app/rpc 白名单内则归 root(/ping 等顶级端点兜底)
     * @param string $path
     * @return string
     */
    public static function ringOfPath(string $path): string
    {
        $segs = array_values(array_filter(explode('/', $path), static fn (string $s): bool => $s !== ''));
        $first = $segs[0] ?? '';
        return in_array($first, Tagger::RINGS, true) ? $first : 'root';
    }

    /**
     * 从 Controller FQCN 第 2 段解析 layer(Lib / Module / Demo);其它一律归 Lib(框架级 controller 兜底)
     * @param string $ctrl
     * @return string
     */
    public static function layerOfController(string $ctrl): string
    {
        $parts = explode('\\', $ctrl);
        $layer = ($parts[0] ?? '') === 'App' ? ($parts[1] ?? '') : '';
        return in_array($layer, Tagger::LAYERS, true) ? $layer : 'Lib';
    }

    /**
     * 从 Controller FQCN 取业务路径段 —— 两种结构都兼容:
     *   1. 标准: `App\{layer}\{L1}[\{L2}]\Controller\...`,biz = layer 之后、Controller 之前的部分
     *   2. 扁平: `App\{layer}\{L1}\{ClassName}`(无 Controller 段,如 App\Lib\Enum\Web),biz = layer 之后剥去末段类名
     * @param string $ctrl
     * @return array<int, string>
     */
    public static function bizPathOfController(string $ctrl): array
    {
        $parts = explode('\\', $ctrl);
        if (($parts[0] ?? '') !== 'App') {
            return [];
        }
        $idx = array_search('Controller', $parts, true);
        if ($idx === false) {
            /** 扁平结构: 剥去末段类名,biz = parts[2..-1) */
            $end = count($parts) - 1;
            return $end <= 2 ? [] : array_slice($parts, 2, $end - 2);
        }
        return $idx <= 2 ? [] : array_slice($parts, 2, $idx - 2);
    }

    /**
     * 拼 tag 内部名,biz 为空时退化为 `{ring}:{layer}`
     * @param string $ring
     * @param string $layer
     * @param array<int, string> $biz
     * @return string
     */
    public static function tagNameOf(string $ring, string $layer, array $biz): string
    {
        $tail = $biz === [] ? '' : ':' . implode(':', $biz);
        return $ring . ':' . $layer . $tail;
    }

    /**
     * tag 显示名 —— 业务层级用空格拼,带上中文 LABEL(若有);
     * 命中 `"Iam Account 账号管理"`,未命中退化为 `"Iam Account"`
     * @param array<int, string> $biz
     * @param string|null $label 业务模块中文备注(由 caller 通过 labelOf 反射取);null 表示无
     * @return string
     */
    public static function tagDisplayOf(array $biz, ?string $label = null): string
    {
        if ($biz === []) {
            return $label ?? '(未分类)';
        }
        $en = implode(' ', $biz);
        return $label === null || $label === '' ? $en : $en . ' ' . $label;
    }

    /**
     * 由 layer + biz 拼出业务模块常量类 FQCN,形如 `App\Module\Iam\Account\Constant\Module`
     * @param string $layer
     * @param array<int, string> $biz
     * @return string
     */
    public static function moduleClassOf(string $layer, array $biz): string
    {
        $segs = array_merge(['App', $layer], $biz, ['Constant', 'Module']);
        return implode('\\', $segs);
    }

    /**
     * 反射读取业务模块常量类的 LABEL 中文名;类不存在或常量缺失时返回 null
     * @param string $moduleClass
     * @return string|null
     */
    public static function labelOf(string $moduleClass): ?string
    {
        if (!class_exists($moduleClass) || !defined($moduleClass . '::LABEL')) {
            return null;
        }
        $label = constant($moduleClass . '::LABEL');
        return is_string($label) && $label !== '' ? $label : null;
    }

    /**
     * 生成 OpenAPI tags + x-tagGroups(Scalar / ReDoc 两级分组,组名形如 `Web 管理后台 - Module`)
     * @param array<string, array<string, array<string, string>>> $ringLayerTags ring => layer => [tagName => displayName]
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    public static function buildTagGroups(array $ringLayerTags): array
    {
        $tags = [];
        $tagGroups = [];
        foreach (Tagger::RING_ORDER as $ring) {
            if (!isset($ringLayerTags[$ring])) {
                continue;
            }
            foreach (Tagger::LAYER_ORDER as $layer) {
                if (!isset($ringLayerTags[$ring][$layer])) {
                    continue;
                }
                $tagMap = $ringLayerTags[$ring][$layer];
                asort($tagMap);
                $tagNames = [];
                foreach ($tagMap as $tagName => $displayName) {
                    $tags[] = [
                        'name' => $tagName,
                        'x-displayName' => $displayName,
                    ];
                    $tagNames[] = $tagName;
                }
                $tagGroups[] = [
                    'name' => (Tagger::RING_LABEL[$ring] ?? ucfirst($ring)) . ' - ' . $layer,
                    'tags' => $tagNames,
                ];
            }
        }
        return [$tags, $tagGroups];
    }
}

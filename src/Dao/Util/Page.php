<?php

declare(strict_types=1);

namespace Hf3\Dao\Util;

/**
 * cursor 分页工具类 —— token 编解码 + 尾页行数 + 边界游标拣选.
 */
final class Page
{
    /**
     * 编码 payload 成 base64(json) 字符串
     *
     * base64(json) 不加密,前端能 decode 看到 payload;仅作 opaque token 语义约定,敏感字段不进 payload.
     *
     * @param array<string, mixed> $payload 边界行的 sort key 值,如 ['create_time' => '2026-...', 'id' => 123]
     * @return string base64 编码的 cursor token
     */
    public static function encode(array $payload): string
    {
        return base64_encode((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * 解码 cursor token 成 payload 数组,无效 token 返 null
     * @param string|null $cursor
     * @return array<string, mixed>|null
     */
    public static function decode(?string $cursor): ?array
    {
        if (superEmpty($cursor)) {
            return null;
        }
        $decoded = json_decode((string) base64_decode($cursor, true), true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * 算尾页 LIMIT 行数 —— last 模式按 total 反推
     *
     * total % size === 0 时尾页是满页(返 size);否则取余数;total <= 0 返 0(跳过 listing).
     *
     * @param int $total 当前 filter 下的总行数
     * @param int $size  每页条数
     * @return int 尾页实际行数(可作为 LIMIT 值;0 表示空表无需查询)
     */
    public static function tailSize(int $total, int $size): int
    {
        if ($total <= 0) {
            return 0;
        }
        $tail = $total % $size;
        return $tail === 0 ? $size : $tail;
    }

    /**
     * 按 page_mode + 是否还有下一页,决定 prev/next cursor 拿哪行作为边界
     *
     * 规则:
     *   - 当前页空 → 都返 null
     *   - first/next: 顺向查询,所以 prev 拿首行(first 例外:首页无 prev),
     *                 next 拿末行(需 nextPage 担保后面还有)
     *   - prev/last:  反向查询(结果已 reverse),所以 prev 拿首行(需 nextPage 担保前面还有),
     *                 next 拿末行(last 例外:尾页无 next)
     *
     * @param string $mode  page_mode 枚举('first'/'next'/'prev'/'last')
     * @param list<array<string, mixed>> $rows 当前页行(已应有的 reverse)
     * @param bool $nextPage Dao 探出来的"查询方向还有更多"
     * @param callable(array<string, mixed>): string $encoder 把行编码成 cursor token 的闭包(通常用 fn => Page::encode([...]))
     * @return array{0: ?string, 1: ?string} [prevCursor, nextCursor]
     */
    public static function cursors(string $mode, array $rows, bool $nextPage, callable $encoder): array
    {
        if ($rows === []) {
            return [null, null];
        }
        return match ($mode) {
            'first' => [null,                                   $nextPage ? $encoder(end($rows)) : null],
            'next'  => [$encoder($rows[0]),                     $nextPage ? $encoder(end($rows)) : null],
            'prev'  => [$nextPage ? $encoder($rows[0]) : null,  $encoder(end($rows))],
            'last'  => [$nextPage ? $encoder($rows[0]) : null,  null],
        };
    }
}

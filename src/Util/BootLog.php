<?php

declare(strict_types=1);

namespace Hf3\Util;

class BootLog
{
    private const int WIDTH = 90;

    private const int KEY_WIDTH = 22;

    /** 并排两卡时单卡宽度(44 + 2 间隔 + 44 = 90,与全宽对齐) */
    private const int HALF_WIDTH = 44;

    /** 并排两卡时的 key 列宽(半宽卡内容窄,缩窄 key 列) */
    private const int HALF_KEY_WIDTH = 12;

    /** 并排两卡之间的水平间隔 */
    private const string PAIR_GAP = '  ';

    private const string ELLIPSIS = '…';

    private const array PALETTE = [
        "\e[1;36m",
        "\e[1;33m",
        "\e[1;35m",
        "\e[1;32m",
        "\e[1;34m",
        "\e[1;31m",
    ];

    private const string C_KEY   = "\e[36m";
    private const string C_DIM   = "\e[2m";
    private const string C_RESET = "\e[0m";

    private static array $buffer = [];

    /**
     * 添加启动日志段落
     * @param string $title
     * @param array $rows
     * @return void
     */
    public static function section(string $title, array $rows): void
    {
        if (!BootLog::enabled()) {
            return;
        }
        BootLog::$buffer[] = ['title' => $title, 'rows' => $rows];
    }

    /**
     * 添加两个并排(左右布局)的段落
     * @param string $titleA
     * @param array $rowsA
     * @param string $titleB
     * @param array $rowsB
     * @return void
     */
    public static function sectionPair(string $titleA, array $rowsA, string $titleB, array $rowsB): void
    {
        if (!BootLog::enabled()) {
            return;
        }
        $cardA = ['title' => $titleA, 'rows' => $rowsA];
        $cardB = ['title' => $titleB, 'rows' => $rowsB];
        BootLog::$buffer[] = ['pair' => [$cardA, $cardB]];
    }

    /**
     * 输出所有段落
     * @return void
     */
    public static function flush(): void
    {
        if (!BootLog::enabled() || BootLog::$buffer === []) {
            BootLog::$buffer = [];
            return;
        }

        echo PHP_EOL;
        $palette = count(BootLog::PALETTE);
        foreach (BootLog::$buffer as $i => $entry) {
            $color = BootLog::PALETTE[$i % $palette];
            if (isset($entry['pair'])) {
                $colorB = BootLog::PALETTE[($i + 1) % $palette];
                BootLog::renderPair($color, $entry['pair'][0], $colorB, $entry['pair'][1]);
            } else {
                BootLog::renderCard($color, $entry['title'], $entry['rows']);
            }
            echo PHP_EOL;
        }
        BootLog::$buffer = [];
    }

    /**
     * 绿色文字
     * @param string $text
     * @return string
     */
    public static function green(string $text): string
    {
        return "\e[32m" . $text . BootLog::C_RESET;
    }

    /**
     * 红色文字
     * @param string $text
     * @return string
     */
    public static function red(string $text): string
    {
        return "\e[31m" . $text . BootLog::C_RESET;
    }

    /**
     * 黄色文字
     * @param string $text
     * @return string
     */
    public static function yellow(string $text): string
    {
        return "\e[33m" . $text . BootLog::C_RESET;
    }

    /**
     * 渲染卡片
     * @param string $color
     * @param string $title
     * @param array $rows
     * @return void
     */
    private static function renderCard(string $color, string $title, array $rows): void
    {
        $lines = BootLog::buildCardLines($color, $title, $rows, BootLog::WIDTH, BootLog::KEY_WIDTH);
        foreach ($lines as $line) {
            echo $line . PHP_EOL;
        }
    }

    /**
     * 并排渲染两张卡片(左右布局)
     * @param string $colorA
     * @param array $cardA ['title' => string, 'rows' => array]
     * @param string $colorB
     * @param array $cardB ['title' => string, 'rows' => array]
     * @return void
     */
    private static function renderPair(string $colorA, array $cardA, string $colorB, array $cardB): void
    {
        /** 先把两卡的内容行补齐到相同行数(空行填充),保证等高、底边对齐 */
        $rowsA = $cardA['rows'];
        $rowsB = $cardB['rows'];
        $maxRows = max(count($rowsA), count($rowsB));
        while (count($rowsA) < $maxRows) {
            $rowsA[] = '';
        }
        while (count($rowsB) < $maxRows) {
            $rowsB[] = '';
        }

        $linesA = BootLog::buildCardLines($colorA, $cardA['title'], $rowsA, BootLog::HALF_WIDTH, BootLog::HALF_KEY_WIDTH);
        $linesB = BootLog::buildCardLines($colorB, $cardB['title'], $rowsB, BootLog::HALF_WIDTH, BootLog::HALF_KEY_WIDTH);

        $rowCount = max(count($linesA), count($linesB));
        $blank = str_repeat(' ', BootLog::HALF_WIDTH);
        for ($i = 0; $i < $rowCount; $i++) {
            $left = $linesA[$i] ?? $blank;
            $right = $linesB[$i] ?? $blank;
            echo $left . BootLog::PAIR_GAP . $right . PHP_EOL;
        }
    }

    /**
     * 构建卡片的所有行(含边框),按指定宽度 —— 返回字符串数组,供单卡 echo 或并排拼接
     * @param string $color
     * @param string $title
     * @param array $rows
     * @param int $width 卡片总宽
     * @param int $keyWidth key 列宽
     * @return array<int, string>
     */
    private static function buildCardLines(string $color, string $title, array $rows, int $width, int $keyWidth): array
    {
        $reset = BootLog::C_RESET;
        $inner = $width - 4;
        $lines = [];

        $titleSeg = ' ' . $title . ' ';
        $titleLen = BootLog::visibleLength($titleSeg);
        $dashCount = max(0, $width - 1 - 2 - $titleLen - 1);
        $lines[] = $color . '╭──' . $reset . $titleSeg . $color . str_repeat('─', $dashCount) . '╮' . $reset;

        if ($rows === []) {
            $lines[] = BootLog::buildRowLine($color, BootLog::C_DIM . '(empty)' . $reset, $inner);
        }
        foreach ($rows as $row) {
            if (is_array($row)) {
                [$k, $v] = $row;
                $content = sprintf(
                    '%s%-' . $keyWidth . 's%s %s',
                    BootLog::C_KEY,
                    $k,
                    $reset,
                    BootLog::stringify($v),
                );
            } else {
                $content = (string) $row;
            }
            $lines[] = BootLog::buildRowLine($color, $content, $inner);
        }

        $lines[] = $color . '╰' . str_repeat('─', $width - 2) . '╯' . $reset;
        return $lines;
    }

    /**
     * 构建单行(含左右边框),按指定内容宽度做截断/补齐
     * @param string $color
     * @param string $content
     * @param int $inner 内容区宽度
     * @return string
     */
    private static function buildRowLine(string $color, string $content, int $inner): string
    {
        $vlen = BootLog::visibleLength($content);
        if ($vlen > $inner) {
            $content = BootLog::truncate($content, $inner);
        } else {
            $content = $content . str_repeat(' ', $inner - $vlen);
        }
        return $color . '│' . BootLog::C_RESET . ' ' . $content . ' ' . $color . '│' . BootLog::C_RESET;
    }

    /**
     * 截断字符串
     * @param string $s
     * @param int $width
     * @return string
     */
    private static function truncate(string $s, int $width): string
    {
        $out = '';
        $visible = 0;
        $cap = $width - 1;
        $i = 0;
        $len = strlen($s);
        while ($i < $len) {

            if ($s[$i] === "\e" && isset($s[$i + 1]) && $s[$i + 1] === '[') {
                $end = strpos($s, 'm', $i + 2);
                if ($end !== false) {
                    $out .= substr($s, $i, $end - $i + 1);
                    $i = $end + 1;
                    continue;
                }
            }
            if ($visible >= $cap) {
                break;
            }

            $first = ord($s[$i]);
            $charLen = match (true) {
                $first < 0x80 => 1,
                $first < 0xC0 => 1,
                $first < 0xE0 => 2,
                $first < 0xF0 => 3,
                default       => 4,
            };
            $out .= substr($s, $i, $charLen);
            $i += $charLen;
            $visible++;
        }
        return $out . BootLog::ELLIPSIS;
    }

    /**
     * 计算可见长度
     * @param string $s
     * @return int
     */
    private static function visibleLength(string $s): int
    {
        $stripped = preg_replace('/\e\[[0-9;]*m/', '', $s) ?? '';
        return mb_strlen($stripped);
    }

    /**
     * 是否启用控制台输出
     * @return bool
     */
    private static function enabled(): bool
    {
        return (string) ($_ENV['LOG_CONSOLE'] ?? 'true') === 'true';
    }

    /**
     * 转为字符串
     * @param mixed $v
     * @return string
     */
    private static function stringify(mixed $v): string
    {
        return match (true) {
            is_bool($v) => $v ? 'true' : 'false',
            $v === null => '(null)',
            default     => (string) $v,
        };
    }
}

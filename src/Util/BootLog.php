<?php

declare(strict_types=1);

namespace Hf3\Util;

class BootLog
{
    private const int WIDTH = 90;

    private const int INNER_WIDTH = 86;

    private const int KEY_WIDTH = 22;

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
        foreach (BootLog::$buffer as $i => $entry) {
            $color = BootLog::PALETTE[$i % count(BootLog::PALETTE)];
            BootLog::renderCard($color, $entry['title'], $entry['rows']);
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
        $reset = BootLog::C_RESET;

        $titleSeg = ' ' . $title . ' ';
        $titleLen = BootLog::visibleLength($titleSeg);
        $dashCount = max(0, BootLog::WIDTH - 1 - 2 - $titleLen - 1);
        echo $color . '╭──' . $reset . $titleSeg . $color . str_repeat('─', $dashCount) . '╮' . $reset . PHP_EOL;

        if ($rows === []) {
            BootLog::renderRow($color, BootLog::C_DIM . '(empty)' . $reset);
        }
        foreach ($rows as $row) {
            if (is_array($row)) {
                [$k, $v] = $row;
                $content = sprintf(
                    '%s%-' . BootLog::KEY_WIDTH . 's%s %s',
                    BootLog::C_KEY,
                    $k,
                    $reset,
                    BootLog::stringify($v),
                );
            } else {
                $content = (string) $row;
            }
            BootLog::renderRow($color, $content);
        }

        echo $color . '╰' . str_repeat('─', BootLog::WIDTH - 2) . '╯' . $reset . PHP_EOL;
    }

    /**
     * 渲染行
     * @param string $color
     * @param string $content
     * @return void
     */
    private static function renderRow(string $color, string $content): void
    {
        $vlen = BootLog::visibleLength($content);
        if ($vlen > BootLog::INNER_WIDTH) {
            $content = BootLog::truncate($content, BootLog::INNER_WIDTH);
        } else {
            $content = $content . str_repeat(' ', BootLog::INNER_WIDTH - $vlen);
        }
        echo $color . '│' . BootLog::C_RESET . ' ' . $content . ' ' . $color . '│' . BootLog::C_RESET . PHP_EOL;
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

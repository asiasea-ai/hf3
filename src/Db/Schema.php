<?php

declare(strict_types=1);

namespace Hf3\Db;

use Hf3\Db\Util\Type;
use Hyperf\DbConnection\Db;

/**
 * 表结构内省 —— 物理表名 → ['col' => ['name','type']] 字典.
 *
 * 走 SHOW FULL COLUMNS,只需该表任意 grant(SELECT 已够),不依赖 INFORMATION_SCHEMA 权限.
 * 单表懒加载,worker 进程内 static cache,二次访问 0 IO.
 */
final class Schema
{
    /** @var array<string, array<string, array{name: string, type: string}>> "{connection}_{table}" → fields */
    private static array $cache = [];

    /**
     * 内省单表结构 —— 物理表名映射成 ['col' => ['name', 'type']] 字典
     *
     * @param string $table
     * @param string $connection
     * @return array<string, array{name: string, type: string}>
     */
    public static function inspect(string $table, string $connection = 'main'): array
    {
        $key = "{$connection}_{$table}";
        if (!isset(Schema::$cache[$key])) {
            Schema::loadOne($table, $connection, $key);
        }
        return Schema::$cache[$key];
    }

    /**
     * 单表 SHOW FULL COLUMNS 拉 schema —— 失败/不存在/非法表名 占位空字典(避免穿透 DB)
     */
    private static function loadOne(string $table, string $connection, string $key): void
    {
        /** 先占位空字典,防止异常路径下二次穿透 DB */
        Schema::$cache[$key] = [];

        /** 防注入 —— 表名只允许字母数字下划线(来自 Model::NAME 常量,加层防御) */
        if (preg_match('/^[a-zA-Z0-9_]+$/', $table) !== 1) {
            return;
        }

        try {
            $rows = Db::connection($connection)->select("SHOW FULL COLUMNS FROM `{$table}`");
        } catch (\Throwable $e) {
            error_log("[Schema] SHOW FULL COLUMNS `{$table}` failed: {$e->getMessage()}");
            return;
        }

        foreach ($rows as $row) {
            $r = (array) $row;
            $col = (string) $r['Field'];
            /** 'int(11) unsigned' / 'varchar(255)' / 'decimal(10,2)' / 'datetime' → 纯类型名 */
            $head = explode(' ', (string) $r['Type'])[0];
            $bare = (string) preg_replace('/\(.*$/', '', strtolower($head));
            $comment = trim((string) (preg_split('/[\r\n（(:：，,；;]/u', (string) $r['Comment'], 2)[0] ?? ''));
            Schema::$cache[$key][$col] = [
                'name' => $comment !== '' ? $comment : $col,
                'type' => Type::scalar($bare),
            ];
        }
    }

    /**
     * 清空所有 connection 的 schema cache —— 测试用
     *
     * @return void
     */
    public static function flush(): void
    {
        Schema::$cache = [];
    }
}

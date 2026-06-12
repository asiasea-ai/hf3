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
     * 内省单表结构 —— 由 Model FQCN 算连接与表名,映射成 ['col' => ['name', 'type']] 字典
     *
     * 连接由 Model::CONNECTION、表名由 Model::NAME 常量自动推导,调用方只传 Model FQCN.
     * 缓存未命中时走 SHOW FULL COLUMNS 拉 schema;失败/不存在/非法表名占位空字典(避免穿透 DB).
     * @param class-string $model Model FQCN
     * @return array<string, array{name: string, type: string}>
     */
    public static function info(string $model): array
    {
        $connection = (string) constant("{$model}::CONNECTION");
        $table = (string) constant("{$model}::NAME");

        $key = "{$connection}_{$table}";
        if (isset(Schema::$cache[$key])) {
            return Schema::$cache[$key];
        }

        /** 先占位空字典,防止异常路径下二次穿透 DB */
        Schema::$cache[$key] = [];

        /** 防注入 —— 表名只允许字母数字下划线(来自 Model::NAME 常量,加层防御) */
        if (preg_match('/^[a-zA-Z0-9_]+$/', $table) !== 1) {
            return Schema::$cache[$key];
        }

        try {
            $rows = Db::connection($connection)->select("SHOW FULL COLUMNS FROM `{$table}`");
        } catch (\Throwable $e) {
            error_log("[Schema] SHOW FULL COLUMNS `{$table}` failed: {$e->getMessage()}");
            return Schema::$cache[$key];
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

        return Schema::$cache[$key];
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

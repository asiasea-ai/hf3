<?php

declare(strict_types=1);

namespace Hf3\Dao\Base;

use Hf3\Dao\Util\Page;
use Hf3\Model\Util\Field;
use Hf3\Throwable\Exception\ErrorException;
use Hf3\Throwable\Exception\WarnException;
use Hyperf\Database\Query\Builder;
use Hyperf\DbConnection\Db;

/**
 * 列表查询(cursor 分页)Dao 拼装父类 —— 两个职责分明的入口.
 *
 * 子类只声明字段配置(MODEL + 三个列名常量),不重复主体.
 * 复杂自定义 WHERE 的表可直接 override where() 逃逸.
 *
 * 调用约定:
 *   - listing 路径:where($params, $mode, $cursor) + orderBy($mode)
 *   - count   路径:where($params)                   ← 不传 $mode,自然跳过 cursor WHERE
 *
 * cursor 排序键固定 (create_time, id);如有别的排序键需求再加 CURSOR_COLUMNS 常量.
 */
abstract class Listing
{
    /** 子类必须声明 Model FQCN —— 用于 delete_flg 列探测;空白时跳过软删过滤 */
    protected const string MODEL = '';

    /** keyword 多列 OR LIKE 字段;默认空,子类按需 override */
    protected const array KEYWORD = [];

    /** IN 精确匹配字段;默认空,子类按需 override */
    protected const array IN = [];

    /** 单字段 LIKE 字段;默认空,子类按需 override */
    protected const array LIKE = [];

    /**
     * 数据库连接 pool 名 —— 子类按需重写(如某张表只读走 'read' 连接).
     * 默认 'main' 跟 BaseModel::$connection 对齐.
     */
    protected static function connection(): string
    {
        return 'main';
    }

    /**
     * 起一个空 QB —— 借用匿名表名 '_' 只为拿到 wheres 累计槽 + grammar,
     * 实际 SQL 由调用方(BaseDao listing 的 raw SELECT 模板)拼接.
     *
     * 子类需要换连接或预挂 JOIN,override 这里即可.
     */
    protected static function qb(): Builder
    {
        return Db::connection(static::connection())->table('_');
    }

    /**
     * WHERE + bindings 拼装 —— filter 永远跑;cursor WHERE 仅在 $pageMode + $pageCursor 都非空时追加.
     *
     * count 路径 调 where($params)  → 只 filter
     * listing 路径 调 where($params, $mode, $cursor) → filter + cursor
     *
     * @param array<string, mixed> $params  过滤条件 bag(keyword / IN / LIKE / time_field 等)
     * @param string|null          $pageMode    'first' / 'next' / 'prev' / 'last';null 时跳过 cursor 块
     * @param string|null          $pageCursor  cursor token(DTO 层已校验,只有 next/prev 才非空)
     * @return array{where: string, bind: list<mixed>}
     */
    public static function where(array $params, ?string $pageMode = null, ?string $pageCursor = null): array
    {
        $qb = static::qb();

        /** keyword 多列 OR LIKE */
        $keyword = $params['keyword'] ?? null;
        $keywordList = static::KEYWORD;
        if (!superEmpty($keyword) && $keywordList !== []) {
            $like = '%' . $keyword . '%';
            $qb->where(static function (Builder $q) use ($keywordList, $like): void {
                foreach ($keywordList as $column) {
                    $q->orWhere($column, 'like', $like);
                }
            });
        }

        /** IN 精确匹配 —— 调用方保证传数组(单值也包成 [v]),空值跳过 */
        foreach (static::IN as $column) {
            $values = $params[$column] ?? null;
            if (!is_array($values) || $values === []) {
                continue;
            }
            $qb->whereIn($column, $values);
        }

        /** 单字段 LIKE */
        foreach (static::LIKE as $column) {
            $value = $params[$column] ?? null;
            if (superEmpty($value)) {
                continue;
            }
            $qb->where($column, 'like', '%' . $value . '%');
        }

        /** 逻辑删除 —— 表有 delete_flg 列就强制 WHERE delete_flg = 0(只查未删行) */
        if (static::MODEL !== '') {
            $deleteField = Field::deleteFlg(Field::select(static::MODEL));
            if (!superEmpty($deleteField)) {
                $qb->where($deleteField, 0);
            }
        }

        /** 时间区间过滤 —— time_field 不传跳过;粒度按字符串长度自适应 */
        $timeField = $params['time_field'] ?? null;
        if (!superEmpty($timeField)) {
            $timeStart = $params['time_start'];
            $timeEnd   = $params['time_end'];
            $qb->where($timeField, '>=', strlen($timeStart) <= 10 ? $timeStart . ' 00:00:00' : $timeStart);
            $qb->where($timeField, '<=', strlen($timeEnd)   <= 10 ? $timeEnd   . ' 23:59:59' : $timeEnd);
        }

        /** cursor WHERE —— 只在 $pageMode 非空 且 $pageCursor 非空时追加
         *  DTO 已校验 cursor 内容合法(含 create_time/id),Dao 信任
         *  目标 SQL: (create_time op X) OR (create_time = X AND id op Y) */
        if ($pageMode !== null && !empty($pageCursor)) {
            $cursor     = Page::decode($pageCursor);
            $op         = $pageMode === 'prev' ? '>' : '<';
            $createTime = $cursor['create_time'];
            $id         = $cursor['id'];

            /** 外层闭包做分组,不污染前面累计的 AND filter */
            $qb->where(static function (Builder $q) use ($op, $createTime, $id): void {
                $q->where('create_time', $op, $createTime);
                $q->orWhere(static function (Builder $q) use ($op, $createTime, $id): void {
                    $q->where('create_time', $createTime);
                    $q->where('id', $op, $id);
                });
            });
        }

        return [
            'where' => (string) stristr($qb->toSql(), 'where '),
            'bind'  => $qb->getBindings(),
        ];
    }

    /**
     * ORDER BY 子句拼装 —— first/next 走 DESC,last/prev 反向 ASC(Dao 层再 array_reverse 回 DESC 给前端).
     *
     * count 路径不调本方法.
     *
     * @param string $pageMode 'first' / 'next' / 'prev' / 'last'
     * @return string  形如 "order by `create_time` desc, `id` desc"
     */
    public static function orderBy(string $pageMode): string
    {
        $direction = in_array($pageMode, ['last', 'prev'], true) ? 'asc' : 'desc';
        $qb = static::qb()
            ->orderBy('create_time', $direction)
            ->orderBy('id', $direction);
        return (string) stristr($qb->toSql(), 'order by ');
    }
}

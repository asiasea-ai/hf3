<?php

declare(strict_types=1);

namespace Hf3\Dao\Base;

use Hf3\Auto\Query;
use Hf3\Dao\Util\Converter;
use Hf3\Dao\Util\Page;
use Hyperf\Database\Query\Builder;
use Hyperf\DbConnection\Db;

/**
 * 列表查询(cursor 分页)Dao 拼装父类 —— 子类只声明字段配置,复杂 WHERE 可 override where() 逃逸.
 */
abstract class Listing
{
    /** keyword 多列 OR LIKE 字段;默认空,子类按需 override */
    protected const array KEYWORD = [];

    /** IN 精确匹配字段;默认空,子类按需 override */
    protected const array IN = [];

    /** 单字段 LIKE 字段;默认空,子类按需 override */
    protected const array LIKE = [];

    /**
     * 起一个空 QB —— 借用匿名表名 '_' 只为拿到 wheres 累计槽 + grammar,
     * 实际 SQL 由调用方(BaseDao listing 的 raw SELECT 模板)拼接.
     *
     * 连接由自身 FQCN 反推 Model 读其 CONNECTION,保证拼装方言与执行库一致;
     * 子类需要预挂 JOIN,override 这里即可.
     * @return Builder
     */
    protected static function qb(): Builder
    {
        $model = Converter::listingToModel(static::class);
        $connection= (string) constant("{$model}::CONNECTION");
        return Db::connection($connection)->table('_');
    }

    /**
     * WHERE + bindings 拼装 —— filter 永远跑;cursor WHERE 仅在 $pageMode + $pageCursor 都非空时追加.
     *
     * count 路径 调 where($params)  → 只 filter
     * listing 路径 调 where($params, $mode, $cursor) → filter + cursor
     *
     * @param array<string, mixed> $params  过滤条件 bag(keyword / IN / LIKE / time_field 等)
     * @param string|null          $mode    'first' / 'next' / 'prev' / 'last';null 时跳过 cursor 块
     * @param string|null          $cursor  cursor token(DTO 层已校验,只有 next/prev 才非空)
     * @return array{where: string, bind: list<mixed>}
     */
    public static function where(array $params, ?string $mode = null, ?string $cursor = null): array
    {
        /** util 转 model */
        $model = Converter::listingToModel(static::class);
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

        /** 自动注入 */
        $autoList = Query::all($model);
        foreach ($autoList as $key => $value) {
            $qb->where($key, $value);
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
         *  排序键固定 (create_time, id);如需别的排序键再加 CURSOR_COLUMNS 常量
         *  DTO 已校验 cursor 内容合法(含 create_time/id),Dao 信任
         *  目标 SQL: (create_time op X) OR (create_time = X AND id op Y) */
        if ($mode !== null && !empty($cursor)) {
            $cursor     = Page::decode($cursor);
            $op         = $mode === 'prev' ? '>' : '<';
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

<?php

declare(strict_types=1);

namespace Hf3\Dao;

use Hf3\Dao\Util\Inspect;
use Hf3\Model\BaseModel;
use Hf3\Model\Util\Field;
use Hf3\Model\Util\Company;
use Hf3\Throwable\Exception\ErrorException;

abstract class BaseDao
{
    public function __construct(
        protected readonly BaseModel $model,
    ) {
    }

    /**
     * 列表查询的强制 WHERE 等值条件 —— Dao 持有 model,据此算出必带的过滤:
     *   - 软删列 delete_flg = 0(只查未删行)
     *   - 公司列 company_id = 当前公司(SaaS 隔离,受管表 fail-closed,无上下文抛错)
     * 返回 [列名 => 值] map,交给 Listing::where 逐条 $qb->where 注入.
     * @return array<string, mixed>
     */
    protected function forcedConditions(): array
    {
        $columns = Field::select($this->model::class);
        $forced  = [];

        $deleteField = Field::deleteFlg($columns);
        if ($deleteField !== '') {
            $forced[$deleteField] = 0;
        }

        [$companyColumn, $companyId] = Company::filter($columns);
        if ($companyColumn !== '') {
            $forced[$companyColumn] = $companyId;
        }

        return $forced;
    }

    /**
     * 列表查询 —— cursor 分页(默认实现)
     *
     * @param array $params 过滤条件 + 分页游标(page_mode / page_cursor)
     * @param int $size 每页条数
     * @return array{list: list<array>, next_page: bool}
     */
    public function listing(array $params, int $size): array
    {
        $table  = $this->model::tableName();
        $mode   = $params['page_mode'];
        $cursor = $params['page_cursor'] ?? null;

        $connection = $this->model::CONNECTION;
        $util = Inspect::listingUtilClass($this->model);
        ['where' => $where, 'bind' => $bind] = $util::where($params, $mode, $cursor, $this->forcedConditions(), $connection);
        $order = $util::orderBy($mode, $connection);

        $cols = Inspect::selectField($this->model);
        /** LIMIT 走整型内联(非参数绑定)—— SelectDB/Doris 不支持 `LIMIT ?` 占位,emulate 模式下会拼成 `LIMIT '51'` 触发语法错误 */
        $limit = $size + 1;
        $sql = "SELECT {$cols} FROM `{$table}` {$where} {$order} LIMIT {$limit}";
        $rows = $this->model->listing($sql, $bind);

        $nextPage = count($rows) > $size;
        if ($nextPage) {
            array_pop($rows);
        }
        if (in_array($mode, ['last', 'prev'], true)) {
            $rows = array_reverse($rows);
        }
        return ['list' => $rows, 'next_page' => $nextPage];
    }

    /**
     * 列表查询总数(默认实现)—— 跟 listing 共享 filter,不带 cursor / ORDER BY
     *
     * @param array $params 过滤条件
     * @return int 当前 filter 下的总行数
     */
    public function count(array $params): int
    {
        $table = $this->model::tableName();
        $util  = Inspect::listingUtilClass($this->model);
        ['where' => $where, 'bind' => $bind] = $util::where($params, null, null, $this->forcedConditions(), $this->model::CONNECTION);

        $sql = "SELECT COUNT(*) AS `count` FROM `{$table}` {$where}";
        $row = $this->model->row($sql, $bind);
        return (int) ($row['count'] ?? 0);
    }

    /**
     * 按主键 id 查询单行,无命中返 null
     *
     * @param int $id 主键
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        return $this->model->find(['id' => $id]);
    }

    /**
     * 新增一行记录,返回新增行主键 id
     *
     * @param array $data 待写入字段
     * @return int 新增行主键 id
     */
    public function save(array $data): int
    {
        return $this->model->save($data);
    }

    /**
     * 按主键 id 更新记录,返回受影响行数
     *
     * @param int $id 主键
     * @param array $patch 待更新字段
     * @return int 受影响行数
     */
    public function updateById(int $id, array $patch): int
    {
        return $this->model->update($patch, ['id' => $id]);
    }

    /**
     * 按主键 id 删除记录,返回受影响行数
     *
     * @param int $id 主键
     * @return int 受影响行数
     */
    public function deleteById(int $id): int
    {
        return $this->model->delete(['id' => $id]);
    }

    /**
     * find —— 单行查询(命中多行抛错),无命中返 null
     * @param array $params
     * @return array|null
     */
    public function find(array $params): ?array
    {
        return $this->model->find($params);
    }
}

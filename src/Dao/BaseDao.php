<?php

declare(strict_types=1);

namespace Hf3\Dao;

use Hf3\Dao\Util\Inspect;
use Hf3\Model\BaseModel;
use Hf3\Throwable\Exception\ErrorException;

abstract class BaseDao
{
    public function __construct(
        protected readonly BaseModel $model,
    ) {
    }

    /**
     * 列表查询 —— cursor 分页(默认实现).
     *
     */
    public function listing(array $params, int $size): array
    {
        $table  = $this->model::tableName();
        $mode   = $params['page_mode'];
        $cursor = $params['page_cursor'] ?? null;

        $util = Inspect::listingUtilClass($this->model);
        ['where' => $where, 'bind' => $bind] = $util::where($params, $mode, $cursor);
        $order = $util::orderBy($mode);

        $cols = Inspect::selectCols($this->model);
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
     * 列表查询总数(默认实现)—— 跟 listing 共享 filter,不带 cursor / ORDER BY.
     */
    public function count(array $params): int
    {
        $table = $this->model::tableName();
        $util  = Inspect::listingUtilClass($this->model);
        ['where' => $where, 'bind' => $bind] = $util::where($params);

        $sql = "SELECT COUNT(*) AS `count` FROM `{$table}` {$where}";
        $row = $this->model->row($sql, $bind);
        return (int) ($row['count'] ?? 0);
    }

    /**
     * findById
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        return $this->model->find(['id' => $id]);
    }

    /**
     * save
     * @param array $data
     * @return int
     */
    public function save(array $data): int
    {
        return $this->model->save($data);
    }

    /**
     * updateById
     * @param int $id
     * @param array $patch
     * @return int
     */
    public function updateById(int $id, array $patch): int
    {
        return $this->model->update($patch, ['id' => $id]);
    }

    /**
     * deleteById
     * @param int $id
     * @return int
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

<?php

declare(strict_types=1);

namespace Hf3\Service;

use Hf3\Dao\BaseDao;
use Hf3\Dao\Util\Page;
use Hf3\Throwable\Exception\ErrorException;

abstract class BaseService
{
    public function __construct(
        protected readonly BaseDao $dao,
    )
    {
    }

    /**
     * listing —— cursor 分页(page_mode/page_cursor 走 $params)
     *
     * @param array<string, mixed> $params
     * @param int $size 每页条数
     * @return array{total: int, list: list<array<string, mixed>>, page_prev: ?string, page_next: ?string}
     */
    public function listing(array $params, int $size): array
    {
        /** 分页处理 */
        $size = max(1, $size);
        $mode = $params['page_mode'];

        /** 拉数据:last 串行(需要 total 算尾页),其它模式 listing/count 并行 */
        if ($mode === 'last') {
            $total = $this->dao->count($params);
            $eff   = Page::tailSize($total, $size);
            $page  = $eff > 0
                ? $this->dao->listing($params, $eff)
                : ['list' => [], 'next_page' => false];
        } else {
            [$page, $total] = waitGroup([
                fn() => $this->dao->listing($params, $size),
                fn() => $this->dao->count($params),
            ]);
        }
        ['list' => $rows, 'next_page' => $nextPage] = $page;

        /** 编码边界 cursor:payload = [create_time, id],前端原样回传 */
        [$prev, $next] = Page::cursors($mode, $rows, $nextPage, static fn(array $r): string => Page::encode([
            'create_time' => $r['create_time'] ?? null,
            'id'          => $r['id']          ?? null,
        ]));
        
        return ['total' => $total, 'list' => $rows, 'page_prev' => $prev, 'page_next' => $next];
    }

    /**
     * 按主键 id 查询单行,无命中返 null
     *
     * @param int $id 主键
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        return $this->dao->findById($id);
    }

    /**
     * 新增一行记录,返回新增行主键 id
     *
     * @param array $params 待写入字段
     * @return int 新增行主键 id
     */
    public function save(array $params): int
    {
        return $this->dao->save($params);
    }

    /**
     * 按主键 id 更新记录,返回受影响行数
     *
     * @param int $id 主键
     * @param array $params 待更新字段
     * @return int 受影响行数
     */
    public function updateById(int $id, array $params): int
    {
        return $this->dao->updateById($id, $params);
    }

    /**
     * 按主键 id 删除记录,返回受影响行数
     *
     * @param int $id 主键
     * @return int 受影响行数
     */
    public function deleteById(int $id): int
    {
        return $this->dao->deleteById($id);
    }

    /**
     * find —— 单行查询(命中多行抛错),无命中返 null
     * @param array $params
     * @return array|null
     */
    public function find(array $params): ?array
    {
        return $this->dao->find($params);
    }
}

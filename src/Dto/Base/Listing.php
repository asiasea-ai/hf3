<?php

declare(strict_types=1);

namespace Hf3\Dto\Base;

use Hf3\Dao\Util\Page;
use Hf3\Spl\SplDto;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * 列表查询 DTO 父类 —— 把 cursor 分页的跨字段校验集中在这,子类不必重复.
 */
abstract readonly class Listing extends SplDto
{
    /**
     * 游标分页跨字段校验 —— page_mode 与 page_cursor 双向绑定 + cursor 内容合法性
     *
     *   1) page_mode 与 page_cursor 双向绑定:next/prev 必带,first/last 必不带
     *   2) cursor 非空时必须能 decode 且含 create_time + id —— Dao 层即可信任
     *
     * Symfony Validator 会自动继承父类的 #[Assert\Callback] 到子类,无需子类显式调用.
     */
    #[Assert\Callback]
    public function validatePageCursor(ExecutionContextInterface $ctx): void
    {
        /** 子类经 SplDto 构造器晋升声明这三个字段:int $page_size / string $page_mode(first/next/prev/last) / ?string $page_cursor(base64(json) token,只在 next/prev 必填) */
        /** cursor 分页:page_mode 与 page_cursor 双向绑定 —— next/prev 必带,first/last 必不带 */
        $needCursor = in_array($this->page_mode, ['next', 'prev'], true);
        if ($needCursor && superEmpty($this->page_cursor)) {
            $ctx->buildViolation("{$this->page_mode} 模式必须传 page_cursor")->atPath('page_cursor')->addViolation();
            return;
        }
        if (!$needCursor && !superEmpty($this->page_cursor)) {
            $ctx->buildViolation("{$this->page_mode} 模式不可传 page_cursor")->atPath('page_cursor')->addViolation();
            return;
        }

        /** cursor 内容合法性:能 decode + 含 create_time/id 两个 sort key */
        if ($needCursor) {
            $cursor = Page::decode($this->page_cursor);
            if (!isset($cursor['create_time'], $cursor['id'])) {
                $ctx->buildViolation('page_cursor 无效')->atPath('page_cursor')->addViolation();
            }
        }
    }
}

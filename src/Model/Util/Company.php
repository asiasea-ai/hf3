<?php

declare(strict_types=1);

namespace Hf3\Model\Util;

use Hf3\Code\Code;
use Hf3\Context\Request;
use Hf3\Throwable\Exception\ErrorException;
use Hyperf\Context\Context;

/**
 * 公司隔离守卫 —— 受管表读写强制带当前公司过滤,缺公司上下文则 fail-closed.
 */
class Company
{
    /** 协程级逃生闸开关 key —— 命中即跳过公司守卫(跨公司/系统操作显式放行) */
    private const string KEY_BYPASS = 'es37_company_bypass';

    /**
     * 取本表的公司隔离列 —— 列名 list 与 etc 候选求交集,0 个返空串(非受管表)、1 个返列名、>1 抛错(schema 错配)
     * @param list<string> $columns 列名 list(常通过 Field::select(static::class) 取得)
     * @return string
     */
    public static function field(array $columns): string
    {
        if (!Company::enabled()) {
            return '';
        }
        $candidates = etc('auto.company.field');
        if (!is_array($candidates)) {
            return '';
        }

        $matched = array_intersect($candidates, $columns);
        $matched = array_values($matched);

        if (count($matched) > 1) {
            throw new ErrorException(
                code: Code::MODEL_COMPANY_AMBIGUOUS,
                message: '表同时命中多个公司隔离列 [' . implode(', ', $matched) . '] —— schema 错配,请只保留一个',
                category: 'model/company',
            );
        }

        return (string) ($matched[0] ?? '');
    }

    /**
     * 公司隔离总开关 —— etc('auto.company.enable'),缺省 true(默认强制隔离)
     * @return bool
     */
    public static function enabled(): bool
    {
        return (bool) etc('auto.company.enable', true);
    }

    /**
     * 取本次应施加的公司过滤 [公司列, 当前公司id] —— 受管表的统一裁决入口.
     *
     * 非受管表(无公司列)/ 总开关关闭 / 逃生闸内 → 返 ['', 0](调用方据此跳过);
     * 受管表但无公司上下文 → fail-closed 抛 MODEL_COMPANY_MISSING(防越权全公司操作).
     * @param list<string> $columns 本表列名 list
     * @return array{0: string, 1: int}
     */
    public static function filter(array $columns): array
    {
        $column = Company::field($columns);
        if ($column === '' || Company::bypassing()) {
            return ['', 0];
        }

        $companyId = Company::current();
        if ($companyId <= 0) {
            throw new ErrorException(
                code: Code::MODEL_COMPANY_MISSING,
                message: '受管表(公司列 ' . $column . ')缺少公司上下文 —— 跨公司/系统操作请用 Company::without 显式放行',
                category: 'model/company',
            );
        }

        return [$column, $companyId];
    }

    /**
     * 从身份对象解析内部公司id —— null 或非数字返 0,否则强转 int
     *
     * ⚠ 风险点:identity.company 是 IAM id_token 的 company claim(VO 标注"对外标识"),
     * 本方法默认该 claim 携带的即内部 company_id 数字.若 IAM 实际下发的是不可直接比对的对外标识,
     * 需在此处补一层"对外标识 → 内部 company_id"映射.
     * @param object|null $identity 当前登录身份(Request::identity())
     * @return int
     */
    public static function resolve(?object $identity): int
    {
        if ($identity === null) {
            return 0;
        }

        $company = $identity->company ?? null;
        if (!is_numeric($company)) {
            return 0;
        }

        return (int) $company;
    }

    /**
     * 取当前请求的内部公司id —— 走 Request::identity() 解析,无登录态返 0
     * @return int
     */
    public static function current(): int
    {
        $identity = Request::identity();
        return Company::resolve($identity);
    }

    /**
     * 当前协程是否处于公司守卫逃生闸内
     * @return bool
     */
    public static function bypassing(): bool
    {
        return (bool) (Context::get(Company::KEY_BYPASS) ?? false);
    }

    /**
     * 逃生闸 —— 在闭包内跳过公司守卫(跨公司查询 / 系统任务 / 登录前流程),执行完恢复原状态
     * @param callable $fn 受保护执行体
     * @return mixed 闭包返回值
     */
    public static function without(callable $fn): mixed
    {
        $previous = Context::get(Company::KEY_BYPASS) ?? false;
        Context::set(Company::KEY_BYPASS, true);
        try {
            return $fn();
        } finally {
            Context::set(Company::KEY_BYPASS, $previous);
        }
    }

    /**
     * 强制注入公司过滤 —— 受管表则用当前公司 id 覆写 row[公司列](防越权传他人公司),非受管/逃生闸则原样返
     *
     * 同时用于 WHERE 条件(find/update/delete)与 INSERT data(save/saveAll).
     * @param list<string> $columns 本表列名 list
     * @param array $row WHERE 条件或写入数据
     * @return array 注入公司后的 row
     */
    public static function enforce(array $columns, array $row): array
    {
        [$column, $companyId] = Company::filter($columns);
        if ($column === '') {
            return $row;
        }

        $row[$column] = $companyId;
        return $row;
    }

    /**
     * 剥离更新数据里的公司列 —— 禁止经 update 把行改到别的公司(逃生闸内放行)
     * @param list<string> $columns 本表列名 list
     * @param array $data 更新数据
     * @return array 剥离公司列后的 data
     */
    public static function strip(array $columns, array $data): array
    {
        if (Company::bypassing()) {
            return $data;
        }

        $column = Company::field($columns);
        if ($column === '') {
            return $data;
        }

        unset($data[$column]);
        return $data;
    }

}

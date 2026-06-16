<?php

declare(strict_types=1);

namespace Hf3\Auto;

use Hf3\Model\Util\Auto;
use Hf3\Model\Util\Field;

/**
 * 写入行预处理推导 —— 由 Model FQCN 逐行消毒 + 租户强制 + 审计补齐.
 */
class Save
{
    /**
     * 预处理待写入行列表 —— 行内三步:消毒 → 公司 → 审计,公共物料循环外备好,整批只遍历一趟
     *
     * - 消毒:过滤非表列 + 没收外部传入的审计字段(由框架重新注入,防伪造)
     * - 公司:受管表强制写入当前公司(覆盖传入,防止越权写他人公司)
     * - 审计:补 create_time / create_account_id,整批共用一个时间戳 + 同一用户
     * @param class-string $model Model FQCN
     * @param array $dataList 待写入行列表
     * @return array 预处理后的行列表
     */
    public static function all(string $model, array $dataList): array
    {
        $companyField = Field::companyId($model);
//        $company = superEmpty($companyField) ? [] : [$companyField => companyId()];
        if (!superEmpty($companyField)) {
            $where[$companyField] ??= companyId();
        }
        $time = Auto::time($model, 'create_time');
        $account = Auto::accountId($model, 'create');

        /** 框架管控的审计列黑名单 —— time + account 两族,外部传入一律没收 */
        $timeField = Auto::timeField();
        $accountField = Auto::accountField();
        $managed = array_merge($timeField, $accountField);
        $managedFlip = array_flip($managed);

        return array_map(
            static function (array $row) use ($model, $company, $time, $account, $managedFlip): array {
                /** 过滤非表列 + 没收外部传入的审计字段 */
                $row = Field::clean($model, $row);
                $row = array_diff_key($row, $managedFlip);

                /** 受管表强制写入当前公司(覆盖传入) */
                $row = $company + $row;

                /** 补审计字段 */
                return $row + $time + $account;
            },
            $dataList,
        );
    }
}

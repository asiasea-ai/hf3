<?php

declare(strict_types=1);

namespace Hf3\Model;

use Hf3\Auto\Delete;
use Hf3\Auto\Save as AutoSave;
use Hf3\Code\Code;
use Hf3\Db\Schema;
use Hf3\Model\Util\Adapter;
use Hf3\Model\Util\Auto;
use Hf3\Model\Util\Field;
use Hf3\Model\Util\Save;
use Hf3\Model\Util\Table;
use Hf3\Safe\Bind;
use Hf3\Safe\Company;
use Hf3\Safe\Inject;
use Hf3\Throwable\Exception\ErrorException;
use Hf3\Throwable\Exception\WarnException;
use Hyperf\Database\Connection;
use Hyperf\DbConnection\Db;

abstract class BaseModel
{
    /** 数据库连接池名 —— 子类按需重写为别的库(如 const CONNECTION = 'report';),Listing 会自动跟随 */
    public const string CONNECTION = 'main';

    public const string PK = 'id';

    /**
     * 获取物理表名 —— 走 Table::get 路由(NORMAL 直返 NAME,PART 分表抛错指向 PartTable)
     *
     * @return string
     */
    public static function tableName(): string
    {
        return Table::get(static::class);
    }

    /**
     * 获取本表列名 list —— 走 Schema::info 取 NAME 对应表的列,连接跟随 CONNECTION
     *
     * schema 不可读(表不存在 / 动态分表无基表)时返空 list.
     * @return list<string>
     */
    public static function fieldList(): array
    {
        $fields = Schema::info(static::class);
        return array_keys($fields);
    }

    /**
     * 获取当前 Model 的枚举字典 —— Schema 当前不返 enum value/alias,本方法永远返 []
     *
     * @return array
     */
    public static function enum(): array
    {
        return [];
    }

    /**
     * 获取字段中文名 —— schema 错配(找不到字段中文名)抛 ErrorException,记日志
     *
     * @param string $key
     * @return string
     */
    public static function fieldName(string $key): string
    {
        $fields = Schema::info(static::class);
        $name = $fields[$key]['name'] ?? null;
        if (superEmpty($name)) {
            throw new ErrorException(Code::FIELD_NOT_FOUND);
        }
        return $name;
    }

    /**
     * 获取本表 schema 字典 —— 走 Schema::info 懒查 + 进程内 static cache,二次访问 0 IO
     * @return array<string, array{name: string, type: string}>
     */
    public static function schemaInfo(): array
    {
        return Schema::info(static::class);
    }

    /**
     * delete —— 软删(若 FIELDS 含 delete_flg)或硬删,WHERE 走 AND 等值
     *
     * @param array $where
     * @param int|null $limit
     * @return int
     */
    final public function delete(array $where, ?int $limit = null): int
    {
        /** 清理删除条件 */
        $columns = static::fieldList();
        $where = Field::clean(static::class, $where);

        /** 删除条件或表字段不能为空 */
        if ($where === [] || $columns === []) {
            throw new ErrorException(Code::MODEL_DELETE_FIELD_NULL);
        }

        /** 受管表强制:WHERE 限定当前公司(覆盖外部传入),防止越权删他人公司 */
        $companyField = Field::companyId(static::class);
        if (!superEmpty($companyField)) {
            $where[$companyField] ??= companyId();
        }

        /** 动态获取表名 */
        $tableName = static::tableName();

        /** 拼接删除条件 */
        $qb = Db::connection(static::CONNECTION)->table($tableName);
        foreach ($where as $key => $value) {
            $qb->where($key, $value);
        }

        /** 限制删除条数 */
        if ($limit !== null) {
            $qb->limit($limit);
        }

        /** sql 检测 —— 软删/物理删共用,先检后删 */
        $sql = $qb->toSql();
        Bind::check($sql);
        Inject::check($sql);
        Company::check($sql, static::class);

        /** 软删 patch —— 空 map 即非软删表,走物理删除 */
        $patch = Delete::all(static::class);
        if ($patch !== []) {
            return $qb->update($patch);
        }

        /** 物理删除 */
        return $qb->delete();
    }

    /**
     * listing —— 裸 SQL 多行查询(复杂 join / 聚合 / 方言)
     *
     * @param string $sql
     * @param array $params
     * @return array
     */
    final public function listing(string $sql, array $params = []): array
    {
        /** SQL 绑定参数检测 */
        Bind::check($sql);
        /** SQL 注入检测 */
        Inject::check($sql);
        /** 公司隔离检测:SQL 涉及的每张受管表都必须按公司列过滤 */
        Company::check($sql, static::class);

        /** 获取查询结果 */
        $rows = Db::connection(static::CONNECTION)->select($sql, $params);
        $rows = array_map(static fn(object $r): array => (array)$r, $rows);

        /** 数据转换*/
        $fields = static::schemaInfo();
        return array_map(static fn(array $row): array => Adapter::castRow($fields, $row), $rows);
    }

    /**
     * row —— 裸 SQL 单行查询
     *
     * @param string $sql
     * @param array $params
     * @return array|null
     */
    final public function row(string $sql, array $params = []): ?array
    {
        /** SQL 绑定参数检测 */
        Bind::check($sql);
        /** SQL 注入检测 */
        Inject::check($sql);
        /** 公司隔离检测:SQL 涉及的每张受管表都必须按公司列过滤 */
        Company::check($sql, static::class);

        /** 获取查询结果 */
        $rows = Db::connection(static::CONNECTION)->select($sql, $params);
        if ($rows === []) {
            return null;
        }

        /** 数据转换*/
        $fields = static::schemaInfo();
        $row = (array)$rows[0];
        return Adapter::castRow($fields, $row);
    }

    /**
     * exec —— 裸 SQL 副作用语句(DDL / 不规则 DML)
     *
     * @param string $sql
     * @param array $params
     * @return int
     */
    final public function exec(string $sql, array $params = []): int
    {
        /** SQL 绑定参数检测 */
        Bind::check($sql);
        /** SQL 注入检测 */
        Inject::check($sql);
        /** 公司隔离检测:SQL 涉及的每张受管表都必须按公司列过滤 */
        Company::check($sql, static::class);

        return Db::connection(static::CONNECTION)->affectingStatement($sql, $params);
    }

    /**
     * update —— 主键禁改;update_time / update_account_id 自动补
     *
     * @param array $data
     * @param array $where
     * @param int|null $numRows
     * @return int
     */
    final public function update(array $data = [], array $where = [], ?int $numRows = null): int
    {
        $data = Field::clean(static::class, $data);
        $where = Field::clean(static::class, $where);

        if ($data === [] || $where === []) {
            return 0;
        }

        if (array_key_exists(static::PK, $data)) {
            throw new ErrorException(
                code: Code::MODEL_PK_IMMUTABLE,
                message: '拒绝更新主键 ' . static::PK,
                category: 'model/pk',
            );
        }

        /** 受管表强制:WHERE 限定当前公司(覆盖外部传入),data 剥离公司列(禁止经 update 改公司归属) */
        $companyField = Field::companyId(static::class);
        if (!superEmpty($companyField)) {
            $where[$companyField] ??= companyId();
            unset($data[$companyField]);
        }

        /** data 仅含公司列时剥离后为空,无可更新字段 */
        if ($data === []) {
            return 0;
        }

        $table = static::tableName();

        $data += Auto::time(static::class, 'update_time');
        $data += Auto::accountId(static::class, 'update');

        $qb = Db::connection(static::CONNECTION)->table($table);
        foreach ($where as $key => $value) {
            $qb->where($key, $value);
        }

        if ($numRows !== null) {
            $qb->limit($numRows);
        }

        $sql = $qb->toSql();
        /** SQL 绑定参数检测 */
        Bind::check($sql);
        /** SQL 注入检测 */
        Inject::check($sql);
        /** 公司隔离检测:SQL 涉及的每张受管表都必须按公司列过滤 */
        Company::check($sql, static::class);

        return $qb->update($data);
    }

    /**
     * save —— 单行 INSERT,返回 lastInsertId
     * @param array $data
     * @return int
     */
    final public function save(array $data): int
    {
        /** 清理无关字段 */
        $data = Field::clean(static::class, $data);

        if ($data === []) {
            throw new ErrorException(Code::MODEL_SAVE_FIELD_NULL);
        }

        /** 受管表强制写入当前公司 —— 覆盖外部传入值,防止越权写他人公司 */
        $companyField = Field::companyId(static::class);
        if (!superEmpty($companyField)) {
            $data[$companyField] ??= companyId();
        }

        /** 自动注入 create_* 审计字段(INSERT 不写 update */
        $data += Auto::time(static::class, 'create_time');
        $data += Auto::accountId(static::class, 'create');

        /** 生成保存 SQL */
        $table = static::tableName();
        ['bind' => $bind, 'sql' => $sql] = Save::all($table, [$data]);

        /** SQL 绑定参数检测 */
        Bind::check($sql);
        /** SQL 注入检测 */
        Inject::check($sql);
        /** 公司隔离检测:SQL 涉及的每张受管表都必须按公司列过滤 */
        Company::check($sql, static::class);

        /** @var Connection $conn */
        $conn = Db::connection(static::CONNECTION);

        /** 入库 */
        $conn->insert($sql, $bind);
        return (int)$conn->getPdo()->lastInsertId();
    }

    /**
     * saveAll —— 批量 INSERT,返回成功插入行数(insert() bool 转计数)
     * @param array $dataList
     * @return int
     */
    final public function saveAll(array $dataList): int
    {
        if ($dataList === []) {
            return 0;
        }

        /** 行预处理 —— 消毒 + 受管表强制公司 + 补审计字段 */
        $dataList = AutoSave::all(static::class, $dataList);

        $table = static::tableName();
        ['bind' => $bind, 'sql' => $sql] = Save::all($table, $dataList);

        /** SQL 绑定参数检测 */
        Bind::check($sql);
        /** SQL 注入检测 */
        Inject::check($sql);
        /** 公司隔离检测:SQL 涉及的每张受管表都必须按公司列过滤 */
        Company::check($sql, static::class);

        return Db::connection(static::CONNECTION)->affectingStatement($sql, $bind);
    }

    /**
     * find —— 单行查询,软删自动过滤,命中多行抛错(强一致语义)
     *
     * $where value 类型决定运算符:scalar → `=`,array → `IN (...)`.
     * 例:['code' => 'X']  → WHERE code = 'X'
     *    ['code' => ['A', 'B']] → WHERE code IN ('A','B')
     *
     * @param array $where
     * @param array $fields
     * @return array|null
     */
    final public function find(array $where = [], array $fields = []): ?array
    {
        /** 清理无关字段 */
        $columns = static::fieldList();
        $where = Field::clean(static::class, $where);

        if ($where === [] || $columns === []) {
            throw new ErrorException(Code::MODEL_FIND_ONE_FIELD_NULL);
        }

        /** 获取逻辑删除 */
        $tableName = static::tableName();
        $deleteField = Field::deleteFlg(static::class);
        if (!superEmpty($deleteField)) {
            $where[$deleteField] ??= 0;
        }

        /** 受管表强制:WHERE 限定当前公司(覆盖外部传入),防止越权查他人公司 */
        $companyField = Field::companyId(static::class);
        if (!superEmpty($companyField)) {
            $where[$companyField] ??= companyId();
        }

        /** 没传查什么字段就给默认 */
        if (superEmpty($fields)) {
            $fields = $columns;
        }

        /** 执行查询 —— scalar 走 where(=),array 走 whereIn */
        $qb = Db::connection(static::CONNECTION)->table($tableName)->select($fields);
        foreach ($where as $col => $val) {
            if (is_array($val)) {
                $qb->whereIn($col, $val);
            } else {
                $qb->where($col, $val);
            }
        }

        /** 公司隔离检测 —— 受管表必须按公司列过滤 */
        Company::check($qb->toSql(), static::class);

        /** 查询结果 */
        $rows = $qb->get()->toArray();

        /** 多条报错 */
        if (count($rows) > 1) {
            throw new ErrorException(
                code: Code::MODEL_FIND_MULTI_ROWS,
                message: 'BaseModel::find() 单行接口命中 ' . count($rows) . ' 行,WHERE = ' . json_encode($where, JSON_UNESCAPED_UNICODE),
                category: 'model/find',
            );
        }

        if ($rows === []) {
            return null;
        }

        $row = (array)$rows[0];
        return Adapter::castRow($fields, $row);
    }
}

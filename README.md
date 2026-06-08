# hf3

> 基于 Hyperf 3.2 / Swoole 协程的微服务基础库。

[![Latest Version](https://img.shields.io/packagist/v/asiasea-ai/hf3.svg)](https://packagist.org/packages/asiasea-ai/hf3)
[![License](https://img.shields.io/packagist/l/asiasea-ai/hf3.svg)](LICENSE)

`Hf3\` 命名空间下的框架级基础设施，为基于 Hyperf 3.2（Swoole 协程，PHP 8.5+）的微服务提供统一的分层骨架、鉴权、限流、异常体系与文档生成能力。

## 环境要求

- PHP >= 8.5
- Swoole / Hyperf 3.2 运行时

## 安装

```bash
composer require asiasea-ai/hf3
```

## 核心能力

| 模块 | 说明 |
|---|---|
| `Spl` / `Dao` / `Service` / `Gateway` / `Model` | `Controller → Gateway → Service → Dao → Model` 分层基类，DTO/VO 基类（`SplDto` / `SplVo`） |
| `Middleware` | 核心中间件：`Hf3Core`（响应信封包装）、`Auth`（JWT 鉴权）、`RateLimit`（限流）、`Trace`（链路追踪） |
| `Identity` | JWT 签发与校验（HS256） |
| `Throwable` | 统一异常体系、错误码映射、PSR 日志桥接 |
| `Db` | 表结构内省（物理表名 → 字段类型字典）、SQL 构建工具 |
| `Doc` | 路由收集与 OpenAPI 文档自动生成 |
| `Policy` | 基于 Casbin 的访问控制网关 |
| `Whitelist` | 路由白名单与 CIDR 匹配 |
| `Curl` | 协程 HTTP / RPC 客户端 |
| `Crontab` / `Process` | 定时任务与自定义进程基类 |
| `Helper` | 协程、环境变量、Redis、身份等全局辅助函数 |

## 响应信封

由 `Hf3Core` 中间件自动包装：

```json
{ "code": 100000, "result": { "data": {} }, "msg": "执行成功", "trace_id": "...", "process_time": "..." }
```

## 许可协议

[MIT](LICENSE)

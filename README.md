# hf3

> TODO: 一句话描述这个 PHP 库的用途

[![Latest Version](https://img.shields.io/packagist/v/asiasea-ai/hf3.svg)](https://packagist.org/packages/asiasea-ai/hf3)
[![License](https://img.shields.io/packagist/l/asiasea-ai/hf3.svg)](LICENSE)

## 环境要求

- PHP >= 8.5

## 安装

```bash
composer require asiasea-ai/hf3
```

## 使用

```php
use AsiaseaAi\Hf3\Hf3;

$hf3 = new Hf3();

echo $hf3->greet();          // Hello, world!
echo $hf3->greet('asiasea'); // Hello, asiasea!
```

## 测试

```bash
composer install
composer test
```

## 许可协议

[MIT](LICENSE)

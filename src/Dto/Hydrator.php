<?php

declare(strict_types=1);

namespace Hf3\Dto;

use Hf3\Code\Code;
use Hf3\Dto\Util\Label;
use Hf3\Throwable\Exception\WarnException;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class Hydrator
{
    private static ?ValidatorInterface $validator = null;

    /**
     * 把输入数组按构造器签名填充并校验, 生成 DTO 实例
     * @param string $dtoClass 目标 DTO 的类名
     * @param array $input 待填充的输入数据
     * @return object 填充并校验后的 DTO 实例
     */
    public static function hydrate(string $dtoClass, array $input): object
    {
        $rc = new \ReflectionClass($dtoClass);
        $ctor = $rc->getConstructor();

        if ($ctor === null) {
            $dto = new $dtoClass();
            Hydrator::runValidate($dto);
            return $dto;
        }

        $dto = Hydrator::buildInstance($rc, $ctor, $input);

        Hydrator::runValidate($dto);

        return $dto;
    }

    /**
     * 运行 DTO 验证
     * @param object $dto
     * @return void
     */
    private static function runValidate(object $dto): void
    {
        $violations = Hydrator::validator()->validate($dto);
        if (count($violations) === 0) {
            return;
        }

        $errors = [];
        $dtoClass = $dto::class;
        foreach ($violations as $v) {

            $path = $v->getPropertyPath();
            $msg = $v->getMessage();
            if ($path === '') {
                $errors[] = $msg;
                continue;
            }
            $label = Label::of($dtoClass, $path) ?? $path;
            $errors[] = "字段[{$label}]{$msg}";
        }
        throw new WarnException(
            code: Code::DTO_INVALID,
            message: implode('; ', $errors),
            category: 'dto',
        );
    }

    /**
     * 构建 DTO 实例
     * @param \ReflectionClass $rc
     * @param \ReflectionMethod $ctor
     * @param array $input
     * @return object
     */
    private static function buildInstance(\ReflectionClass $rc, \ReflectionMethod $ctor, array $input): object
    {
        $args = [];
        $errors = [];
        $dtoClass = $rc->getName();

        foreach ($ctor->getParameters() as $p) {
            $name = $p->getName();
            $type = $p->getType();
            $hasInput = array_key_exists($name, $input);

            /** 只有真正未传(JSON 里没这个 key)才用默认值;显式传 "" / null 都按用户意图保留 */
            if (!$hasInput) {
                if ($p->isDefaultValueAvailable()) {
                    $args[] = $p->getDefaultValue();
                    continue;
                }
                if ($type instanceof \ReflectionNamedType && $type->allowsNull()) {
                    $args[] = null;
                    continue;
                }
                $label = Label::of($dtoClass, $name) ?? $name;
                $errors[] = "字段[{$label}]必填";
                $args[] = null;
                continue;
            }

            $value = $input[$name];

            /** 显式传 null 但字段不允许 null —— 报错(Symfony Assert\NotBlank 等也会接力校验) */
            if ($value === null && !($type instanceof \ReflectionNamedType && $type->allowsNull())) {
                $label = Label::of($dtoClass, $name) ?? $name;
                $errors[] = "字段[{$label}]不能为 null";
                $args[] = null;
                continue;
            }

            /** 标量类型按声明强转(string/int/float/bool);null 直接保留 */
            if ($value !== null && $type instanceof \ReflectionNamedType && $type->isBuiltin()) {
                $value = match ($type->getName()) {
                    'string' => (string) $value,
                    'int'    => (int) $value,
                    'float'  => (float) $value,
                    'bool'   => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                    default  => $value,
                };
            }
            $args[] = $value;
        }

        if ($errors !== []) {
            throw new WarnException(
                code: Code::DTO_INVALID,
                message: implode('; ', $errors),
                category: 'dto',
            );
        }

        return $rc->newInstanceArgs($args);
    }

    /**
     * 获取验证器实例
     * @return ValidatorInterface
     */
    private static function validator(): ValidatorInterface
    {
        if (Hydrator::$validator === null) {
            $translator = new Translator('zh_CN');
            $translator->addLoader('xlf', new XliffFileLoader());
            $translator->addResource(
                'xlf',
                BASE_PATH . '/vendor/symfony/validator/Resources/translations/validators.zh_CN.xlf',
                'zh_CN',
            );
            Hydrator::$validator = Validation::createValidatorBuilder()
                ->enableAttributeMapping()
                ->setTranslator($translator)
                ->getValidator();
        }
        return Hydrator::$validator;
    }
}

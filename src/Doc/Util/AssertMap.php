<?php

declare(strict_types=1);

namespace Hf3\Doc\Util;

use ReflectionAttribute;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Symfony Validator Assert 注解 → OpenAPI 字段约束映射.
 */
final class AssertMap
{
    /**
     * 把一个 promoted 参数上的所有 Assert 注解汇总成 OpenAPI 字段约束.
     * @param array<int, ReflectionAttribute> $attrs ReflectionParameter::getAttributes() 返回值
     * @return array{constraints: array<string, mixed>, required: bool}
     *   constraints — 直接 merge 进 OpenAPI property schema 的键值对
     *   required    — 是否有 NotBlank,需叠加到外层 schema.required[]
     */
    public static function fromAttributes(array $attrs): array
    {
        $constraints = [];
        $required = false;

        foreach ($attrs as $attr) {
            $name = $attr->getName();
            $args = $attr->getArguments();

            $piece = AssertMap::translate($name, $args);
            if ($piece === null) {
                continue;
            }
            if (($piece['__required'] ?? false) === true) {
                $required = true;
                unset($piece['__required']);
            }
            $constraints = array_merge($constraints, $piece);
        }

        return ['constraints' => $constraints, 'required' => $required];
    }

    /**
     * 把单个 Assert 注解 FQCN + 参数翻译成 OpenAPI 片段.
     * @param string $fqcn
     * @param array $args
     * @return array<string, mixed>|null  null 表示该注解不识别,跳过
     */
    private static function translate(string $fqcn, array $args): ?array
    {
        return match ($fqcn) {
            Assert\NotBlank::class    => ['__required' => true],
            Assert\Choice::class      => AssertMap::choice($args),
            Assert\Length::class      => AssertMap::length($args),
            Assert\Range::class       => AssertMap::range($args),
            Assert\Regex::class       => AssertMap::regex($args),
            Assert\Email::class       => ['format' => 'email'],
            Assert\Url::class         => ['format' => 'uri'],
            Assert\Uuid::class        => ['format' => 'uuid'],
            Assert\Date::class        => ['format' => 'date'],
            Assert\DateTime::class    => ['format' => 'date-time'],
            Assert\Positive::class    => ['minimum' => 1],
            Assert\PositiveOrZero::class => ['minimum' => 0],
            Assert\Negative::class    => ['maximum' => -1],
            Assert\NegativeOrZero::class => ['maximum' => 0],
            Assert\Count::class       => AssertMap::count($args),
            default                   => null,
        };
    }

    /**
     * Choice → enum
     * @param array $args
     * @return array<string, mixed>
     */
    private static function choice(array $args): array
    {
        $choices = $args['choices'] ?? $args[0] ?? null;
        if (!is_array($choices) || $choices === []) {
            return [];
        }
        return ['enum' => array_values($choices)];
    }

    /**
     * Length → minLength / maxLength
     * @param array $args
     * @return array<string, mixed>
     */
    private static function length(array $args): array
    {
        $out = [];
        if (isset($args['min']) && is_int($args['min'])) {
            $out['minLength'] = $args['min'];
        }
        if (isset($args['max']) && is_int($args['max'])) {
            $out['maxLength'] = $args['max'];
        }
        return $out;
    }

    /**
     * Range → minimum / maximum
     * @param array $args
     * @return array<string, mixed>
     */
    private static function range(array $args): array
    {
        $out = [];
        if (isset($args['min']) && is_numeric($args['min'])) {
            $out['minimum'] = $args['min'];
        }
        if (isset($args['max']) && is_numeric($args['max'])) {
            $out['maximum'] = $args['max'];
        }
        return $out;
    }

    /**
     * Regex → pattern(剥两侧 / 和 flag)
     * @param array $args
     * @return array<string, mixed>
     */
    private static function regex(array $args): array
    {
        $pattern = $args['pattern'] ?? $args[0] ?? null;
        if (!is_string($pattern) || $pattern === '') {
            return [];
        }
        $stripped = preg_replace('#^/(.+)/[a-z]*$#u', '$1', $pattern);
        return ['pattern' => (string) $stripped];
    }

    /**
     * Count → minItems / maxItems
     * @param array $args
     * @return array<string, mixed>
     */
    private static function count(array $args): array
    {
        $out = [];
        if (isset($args['min']) && is_int($args['min'])) {
            $out['minItems'] = $args['min'];
        }
        if (isset($args['max']) && is_int($args['max'])) {
            $out['maxItems'] = $args['max'];
        }
        return $out;
    }
}

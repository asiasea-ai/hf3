<?php

declare(strict_types=1);

namespace AsiaseaAi\Hf3\Tests;

use AsiaseaAi\Hf3\Hf3;
use PHPUnit\Framework\TestCase;

final class Hf3Test extends TestCase
{
    public function test_greet_returns_default_greeting(): void
    {
        $this->assertSame('Hello, world!', (new Hf3())->greet());
    }

    public function test_greet_accepts_a_name(): void
    {
        $this->assertSame('Hello, asiasea!', (new Hf3())->greet('asiasea'));
    }
}

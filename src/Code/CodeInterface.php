<?php

declare(strict_types=1);

namespace Hf3\Code;

interface CodeInterface extends \BackedEnum
{
    public function message(): string;
}

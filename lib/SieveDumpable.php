<?php

declare(strict_types=1);

namespace Sieve;

interface SieveDumpable
{
    public function dump(): string;
    public function text(): string;
}

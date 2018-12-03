<?php

declare(strict_types=1);

namespace Sieve;

interface SieveDumpable
{
    public function dump();
    public function text();
}

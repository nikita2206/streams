<?php

namespace Streams\Predicate;

use Streams\StreamProcessor;

interface Predicate extends StreamProcessor
{
    /**
     * @param mixed $value
     * @return bool
     */
    public function test($value): bool;
}

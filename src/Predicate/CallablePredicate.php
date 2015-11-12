<?php

namespace Streams\Predicate;

class CallablePredicate implements Predicate
{
    protected $cb;

    public function __construct(callable $cb)
    {
        $this->cb = $cb;
    }

    /**
     * @inheritdoc
     */
    public function test($value): bool
    {
        return call_user_func($this->cb, $value);
    }
}

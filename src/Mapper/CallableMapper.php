<?php

namespace Streams\Mapper;

class CallableMapper implements Mapper
{

    protected $cb;

    public function __construct(callable $cb)
    {
        $this->cb = $cb;
    }

    /**
     * @inheritdoc
     */
    public function map($value)
    {
        return call_user_func($this->cb, $value);
    }
}

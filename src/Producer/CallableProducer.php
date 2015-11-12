<?php

namespace Streams\Producer;

class CallableProducer implements Producer
{
    protected $cb;

    public function __construct(callable $cb)
    {
        $this->cb = $cb;
    }

    /**
     * @inheritdoc
     */
    public function produce($v)
    {
        return call_user_func($this->cb, $v);
    }
}

<?php

namespace Streams\Producer;

use Streams\StreamProcessor;

interface Producer extends StreamProcessor
{
    /**
     * @param mixed $value
     * @return array|\Traversable
     */
    public function produce($value);
}

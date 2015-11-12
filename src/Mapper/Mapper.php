<?php

namespace Streams\Mapper;

use Streams\StreamProcessor;

interface Mapper extends StreamProcessor
{
    /**
     * @param mixed $value
     * @return mixed
     */
    public function map($value);
}

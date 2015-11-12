<?php

namespace Streams;

use Streams\Mapper\CallableMapper;
use Streams\Mapper\Mapper;
use Streams\Predicate\CallablePredicate;
use Streams\Predicate\Predicate;
use Streams\Producer\CallableProducer;
use Streams\Producer\Producer;

class Stream implements \IteratorAggregate
{

    /**
     * @var array|\Traversable
     */
    protected $data;

    /**
     * @var StreamProcessor[]
     */
    protected $chain;

    /**
     * @var bool
     */
    protected $compile;

    /**
     * @var \Closure
     */
    protected $compiled;

    /**
     * @param array|\Traversable $data
     */
    public function __construct($data)
    {
        $this->data = $data;
        $this->compile = false;
    }

    /**
     * @param array|\Traversable $data
     * @return Stream
     */
    public static function from($data)
    {
        return new Stream($data);
    }

    /**
     * @param callable $cb
     * @return $this
     */
    public function filter(callable $cb)
    {
        $this->chain[] = new CallablePredicate($cb);
        return $this;
    }

    /**
     * @param callable $cb
     * @return $this
     */
    public function map(callable $cb)
    {
        $this->chain[] = new CallableMapper($cb);
        return $this;
    }

    /**
     * @param callable $cb
     * @return $this
     */
    public function produce(callable $cb)
    {
        $this->chain[] = new CallableProducer($cb);
        return $this;
    }

    /**
     * @param callable(mixed $value, T $accumulated): T $cb
     * @param T $startFrom
     * @return T
     */
    public function reduce(callable $cb, $startFrom = null)
    {
        $accumulated = $startFrom;
        foreach ($this->getIterator() as $value) {
            $accumulated = $cb($value, $accumulated);
        }

        return $accumulated;
    }

    /**
     * @param StreamProcessor $processor
     * @return $this
     */
    public function add(StreamProcessor $processor)
    {
        $this->chain[] = $processor;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getIterator()
    {
        if ($this->compile) {
            $closure = $this->compiled;
            return $closure($this->data);
        } else {
            return $this->process($this->data, $this->chain);
        }
    }

    /**
     * @return $this
     */
    public function compile()
    {
        $code = 'return function ($data) {';
        foreach ($this->chain as $idx => $processor) {
            $code .= "\$p{$idx} = \$this->chain[{$idx}];";
        }

        $code .= 'foreach ($data as $key => $value) {';

        $layers = 0;
        foreach ($this->chain as $idx => $processor) {
            if ($processor instanceof Mapper) {
                $code .= "\$value = \$p{$idx}->map(\$value);";
            } elseif ($processor instanceof Predicate) {
                $code .= "if (!\$p{$idx}->test(\$value)) continue;";
            } elseif ($processor instanceof Producer) {
                $code .= "foreach (\$p{$idx}->produce(\$value) as \$key => \$value) {";
                $layers++;
            }
        }

        for ($i = 0; $i <= $layers; $i++) {
            if ($i === 0) {
                $code .= 'yield $key => $value; }';
            } else {
                $code .= '}';
            }
        }

        $code .= ' }; ';

        $this->compiled = eval($code);
        $this->compile = true;

        return $this;
    }

    protected function process($data, $chain)
    {
        foreach ($data as $key => $value) {
            foreach ($chain as $procIdx => $processor) {
                if ($processor instanceof Mapper) {
                    $value = $processor->map($value);
                } elseif ($processor instanceof Predicate) {
                    if ( ! $processor->test($value)) {
                        goto skip;
                    }
                } elseif ($processor instanceof Producer) {
                    yield from $this->process($processor->produce($value), array_slice($chain, $procIdx + 1));
                    goto skip;
                }
            }

            yield $key => $value;
            skip:
        }
    }
}

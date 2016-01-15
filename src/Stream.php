<?php

namespace Streams;

use PhpOption\None;
use PhpOption\Some;
use Streams\Mapper\CallableMapper;
use Streams\Mapper\Mapper;
use Streams\Predicate\CallablePredicate;
use Streams\Predicate\Predicate;
use Streams\Producer\CallableProducer;
use Streams\Producer\Producer;

class Stream implements \IteratorAggregate
{

    public static $compileByDefault = true;

    public static $compileAfterThreshold = 3;

    /**
     * @var array|\Traversable
     */
    protected $data;

    /**
     * @var StreamProcessor[]
     */
    protected $processors;

    /**
     * @var bool
     */
    protected $compile;

    /**
     * @var \Closure
     */
    protected $compiled;

    /**
     * @var bool
     */
    protected $terminated;

    /**
     * @var int
     */
    protected $maxSize;

    /**
     * @var int
     */
    protected $startingFrom;

    /**
     * @param array|\Traversable $data
     */
    public function __construct($data)
    {
        $this->data = $data;
        $this->compile = false;
        $this->terminated = false;
    }

    /**
     * @param array|\Traversable $data
     * @return Stream
     */
    public static function from($data)
    {
        return new Stream($data);
    }

    public function allMatch(callable $predicate): bool
    {
        foreach ($this as $element) {
            if ( ! $predicate($element)) {
                return false;
            }
        }

        return true;
    }

    public function anyMatch(callable $predicate): bool
    {
        foreach ($this as $element) {
            if ($predicate($element)) {
                return true;
            }
        }

        return false;
    }

    public function count(): int
    {
        if ( ! $this->processors && (is_array($this->data) || $this->data instanceof \Countable)) {
            return count($this->data);
        }

        $result = 0;
        foreach ($this as $_) {
            $result++;
        }

        return $result;
    }

    public function filter(callable $predicate)
    {
        $this->processors[] = $predicate instanceof Predicate ? $predicate : new CallablePredicate($predicate);
        return $this;
    }

    public function map(callable $mapper)
    {
        $this->processors[] = $mapper instanceof Mapper ? $mapper : new CallableMapper($mapper);
        return $this;
    }

    public function flatMap(callable $producer)
    {
        $this->processors[] = $producer instanceof Producer ? $producer : new CallableProducer($producer);
        return $this;
    }

    public function limit(int $maxSize)
    {
        $this->maxSize = $maxSize;
        return $this;
    }

    public function skip(int $n)
    {
        $this->startingFrom = $n;
        return $this;
    }

    public function findFirst()
    {
        foreach ($this as $el) {
            return new Some($el);
        }

        return None::create();
    }

    /**
     * @param callable(T $value, T $accumulated): T $cb
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

    public function add(StreamProcessor $processor)
    {
        $this->processors[] = $processor;
        return $this;
    }

    public function getIterator()
    {
        if ($this->compile) {
            $closure = $this->compiled;
            return $closure($this->data);
        } else {
            $skip = $this->startingFrom === null ? 0 : $this->startingFrom;
            $processed = 0;

            return $this->process($this->data, $this->processors, $skip, $processed);
        }
    }

    public function compile()
    {
        $this->terminate();

        $code = 'return function ($data) {';
        foreach ($this->processors as $idx => $_) {
            $code .= "\$p{$idx} = \$this->processors[{$idx}];";
        }

        if ($this->startingFrom) {
            $code .= '$skip = $this->startingFrom;';
        }
        if ($this->maxSize) {
            $code .= '$limit = $this->maxSize;';
        }
        $code .= 'foreach ($data as $key => $value) {';

        $layers = 0;
        foreach ($this->processors as $idx => $processor) {
            if ($processor instanceof Mapper) {
                $code .= "\$value = \$p{$idx}->map(\$value);";
            } elseif ($processor instanceof Predicate) {
                $code .= "if (!\$p{$idx}->test(\$value)) continue;";
            } elseif ($processor instanceof Producer) {
                $code .= "foreach (\$p{$idx}->produce(\$value) as \$key => \$value) {";
                $layers++;
            }
        }

        if ($this->startingFrom) {
            $code .= 'if ($skip) {' .
                         '--$skip;' .
                         'continue;' .
                     '}';
        }
        if ($this->maxSize) {
            $code .= 'if ($limit-- === 0) {' .
                         'return;' .
                     '}';
        }

        $code .= 'yield $key => $value; }';
        $code .= str_repeat('}', $layers);

        $code .= ' };';

        $this->compiled = eval($code);
        $this->compile = true;

        return $this;
    }

    protected function process($data, $chain, &$skip = 0, &$processed = 0)
    {
        $this->terminate();
        $limit = $this->maxSize;

        foreach ($data as $key => $value) {
            foreach ($chain as $procIdx => $processor) {
                if ($processor instanceof Mapper) {
                    $value = $processor->map($value);
                } elseif ($processor instanceof Predicate) {
                    if ( ! $processor->test($value)) {
                        goto skip;
                    }
                } elseif ($processor instanceof Producer) {
                    yield from $this->process($processor->produce($value), array_slice($chain, $procIdx + 1), $skip, $processed);

                    if ($processed > $limit) {
                        return;
                    }
                    goto skip;
                }
            }

            if ($skip) {
                --$skip;
                continue;
            }

            if ($processed > $limit) {
                return;
            }

            ++$processed;
            yield $key => $value;
            skip:
        }
    }

    protected function terminate($onlyCheck = false)
    {
        if ($this->terminated) {
            throw new IllegalStateException("This stream was already terminated");
        }

        if ( ! $onlyCheck) {
            $this->terminated = true;
        }
    }
}

<?php

require __DIR__ . "/src/Stream.php";
require __DIR__ . "/src/StreamProcessor.php";
require __DIR__ . "/src/Mapper/Mapper.php";
require __DIR__ . "/src/Mapper/CallableMapper.php";
require __DIR__ . "/src/Predicate/Predicate.php";
require __DIR__ . "/src/Predicate/CallablePredicate.php";
require __DIR__ . "/src/Producer/Producer.php";
require __DIR__ . "/src/Producer/CallableProducer.php";

$randProducer = function ($value) {
    for ($i = 0; $i < $value; $i++) {
        yield mt_rand(0, ($i + 1) * 100);
    }
};

$numbers = ["zero", "one", "two", "three", "four", "five", "six", "seven", "eight", "nine"];
$numberformatter = function ($v) use ($numbers) {
    $v = (string)$v;
    $str = [];

    for ($i = 0, $l = strlen($v); $i < $l; ++$i) {
        $str[] = $numbers[$v[$i]];
    }

    return implode(" ", $str);
};

$numbersReversed = array_flip($numbers);
$numberReverseFormatter = function ($v) use ($numbersReversed) {
    return $numbersReversed[$v];
};

$stuff = \Streams\Stream::from([1, 2, 3, 8, 5, 20, 131, 3425, 134])
    ->flatMap($randProducer)
    ->flatMap($randProducer)
    ->filter(function ($v) { return !($v % 3) || !($v % 2); })
    ->map($numberformatter)
    ->flatMap(function ($formatted) { return explode(" ", $formatted); })
    ->map($numberReverseFormatter)
    ->skip(1000)
    ->limit(200)
    ->compile()
;

//echo $stuff->compile(), "\n\n";

//echo $stuff->reduce(function ($v, $acc) { return $v + $acc; }, 0), "\n";
foreach ($stuff as $formatted) {
    echo $formatted, "\n";
}

echo (memory_get_peak_usage() / 1024), "kb\n";

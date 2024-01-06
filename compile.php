<?php

error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

if (file_exists($argv[1])) {
    echo \hafriedlander\Peg\Compiler::compile(file_get_contents($argv[1]));
}

?>

<?php
/*
 * This is part of jcubic/expression package
 * Copyright (c) 2024 Jakub T. Jankiewicz <https://jcu.bi>
 * Released under MIT license
 *
 * This is Expression class witch is an Adapter for the Parser generated by PEG
 */

namespace jcubic;


class __Expression {
    private $constants;
    public $variables = [];
    public $functions = [];
    public $suppress_errors = false;
    private $expr = null;
    function __construct() {
        $this->constants = ["e" => exp(1), "pi" => M_PI];
    }
    function evaluate($expr) {
        $this->expr = new Parser($expr, $this->variables, $this->constants, $this->functions);
        $res = $this->expr->match_Start();
        if ($res === FALSE) {
            throw new \Exception("invalid syntax $expr");
        }
        $this->variables = &$this->expr->variables;
        $this->functions = &$this->expr->functions;
        return $res['val'];
    }
}

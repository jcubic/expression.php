<?php

namespace jcubic;

class __Expression {
    private $constants;
    public $variables = [];
    private $expr = null;
    function __construct() {
        $this->constants = ["e" => exp(1), "pi" => M_PI];
    }
    function evaluate($expr) {
        $this->expr = new Parser($expr, $this->variables, $this->constants);
        $res = $this->expr->match_Start();
        if ($res === FALSE) {
            throw new \Exception("invalid syntax $expr");
        }
        $this->variables = &$this->expr->variables;
        return $res['val'];
    }
}

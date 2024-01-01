<?php

namespace jcubic;

class __Expression {
    public $variables = [];
    private $expr = null;
    function evaluate($expr) {
        $this->$expr = new Parser($expr);
        $this->$expr->variables = &$this->variables;
        $res = $this->$expr->match_Start();
        if ($res === FALSE) {
            throw new \Exception("invalid syntax $expr");
        }
        $this->variables = &$this->$expr->variables;
        return $res['val'];
    }
}

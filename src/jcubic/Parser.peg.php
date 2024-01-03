<?php
/*
 * This is part of jcubic/expression package
 * Copyright (c) 2024 Jakub T. Jankiewicz <https://jcu.bi>
 * Released under MIT license
 *
 * This is Parser PEG grammar PEG
 */

namespace jcubic;

use hafriedlander\Peg;
use ReflectionFunction;

/*

TODO: regular expressions + string regex
      JSON objects / JSON comparison == !=
      boolean comparators == != < > <= >=
      unary negation
      boolean && and || - like in JavaScript
      strings - single double
      bit shift (new)
      match operator =~
      modulo
      strip semicolons
*/

class Parser extends Peg\Parser\Basic {
    public $variables;
    public $functions;
    private $builtin_functions = [
        'sin','sinh','arcsin','asin','arcsinh','asinh',
        'cos','cosh','arccos','acos','arccosh','acosh',
        'tan','tanh','arctan','atan','arctanh','atanh',
        'sqrt','abs','ln','log'
    ];
    private $constants;
    public function __construct($expr, &$variables, &$constants, &$functions) {
        parent::__construct($expr);
        $this->variables = $variables;
        $this->constants = $constants;
        $this->functions = $functions;
    }
    private function is_regex($value) {
        return is_array($value) && $value['type'] == 'regex';
    }

/*!* Expressions

SimpleRegExp: /(?<!\\\\)\/(?:[^\/]|\\\\\/)+\// /[imsxUXJ]/*
StringRegExp: q:/['"]/ mark:/\w/ string '$mark' /[imsxUXJ]/* '$q'
RegExp: SimpleRegExp | StringRegExp
    function SimpleRegExp(&$result, $sub) {
        $result['val'] = $sub['text'];
    }
    function StringRegExp(&$result, $sub) {
        $result['val'] = $sub['text'];
    }

Name: "$"? /[A-Za-z]+/
Variable: Name
   function Name(&$result, $sub) {
       $result['val'] = $sub['text'];
   }

Consts: "true" | "false" | "null"
Number: /[0-9.]+e[0-9]+|[0-9]+(?:\.[0-9]*)?|\.[0-9]+/
Value: Consts > | RegExp > | Variable > | Number > | '(' > Expr > ')' >
    function Consts(&$result, $sub) {
        $result['val'] = json_decode($sub['text']);
    }
    function Variable(&$result, $sub) {
        $name = $sub['val'];
        if (array_key_exists($name, $this->constants)) {
            $result['val'] = $this->constants[$name];
        } else if (array_key_exists($name, $this->variables)) {
            $result['val'] = $this->variables[$name];
        } else {
            throw new \Exception("Variable '$name' not found");
        }
    }
    function RegExp(&$result, $sub) {
        $result['val'] = ["type" => "regex", "value" => $sub['val']];
    }
    function Number(&$result, $sub) {
        $result['val'] = floatval($sub['text']);
    }
    function Expr(&$result, $sub ) {
        $result['val'] = $sub['val'];
    }

Call: Name "(" > ( > Expr > ","? > ) * > ")" >
   function Name(&$result, $sub) {
       $name = $sub['text'];
       $result['val'] = [
           "args" => [],
           "name" => $name
       ];
   }
   function Expr(&$result, $sub) {
       array_push($result['val']['args'], $sub['val']);
   }


Negation: '-' > operand:Value >
ToInt: '+' > operand:Value >
Unnary: (Call | Negation | ToInt | Value )
   function ToInt(&$result, $sub) {
        $val = $sub['operand']['val'];
        if (is_string($val)) {
            $val = floatval($val);
        }
        $result['val'] = $val;
   }
   function Call(&$result, $sub) {
       $name = $sub['val']['name'];
       $name = preg_replace('/^arc/', 'a', $name);
       $is_builtin = in_array($name, $this->builtin_functions);
       $is_custom = array_key_exists($name, $this->functions);
       if (!$is_builtin && !$is_custom) {
           throw new \Exception("function '$name' doesn't exists");
       }
       $args = $sub['val']['args'];
       $args_count = count($args);
       if ($is_builtin && $name == "ln") {
           $name = "log";
       }
       $function = new ReflectionFunction($is_builtin ? $name : $this->functions[$name]);
       $params_require_count = $function->getNumberOfRequiredParameters();
       $params_all_count = $function->getNumberOfParameters();
       if ($args_count < $params_require_count && $args_count > $params_all_count) {
           throw new \Exception("Function '$name' expected $params_count params got $args_count");
       }
       $result['val'] = $function->invokeArgs($args);
   }
   function Value(&$result, $sub) {
       $result['val'] = $sub['val'];
   }
   function Negation(&$result, $sub) {
       $result['val'] = $sub['operand']['val'] * -1;
   }

Times: '*' > operand:Unnary >
Div: '/' > operand:Unnary >
Mod: '%' > operand:Unnary >
Product: Unnary > ( Times | Div | Mod ) *
    function Unnary(&$result, $sub) {
        $result['val'] = $sub['val'];
    }
    function Times(&$result, $sub) {
        $result['val'] *= $sub['operand']['val'];
    }
    function Div(&$result, $sub) {
        $result['val'] /= $sub['operand']['val'];
    }
    function Mod(&$result, $sub) {
        $result['val'] %= $sub['operand']['val'];
    }

Plus: '+' > operand:Product >
Minus: '-' > operand:Product >
Sum: Product > ( Plus | Minus ) *
    function Product(&$result, $sub) {
        $result['val'] = $sub['val'];
    }
    function Plus(&$result, $sub) {
        $result['val'] += $sub['operand']['val'];
    }
    function Minus(&$result, $sub) {
        $result['val'] -= $sub['operand']['val'];
    }

VariableAssignment: Variable > "=" > Expr
    function Variable(&$result, $sub) {
        $result['val'] = ["name" => $sub['val']];
    }
    function Expr(&$result, $sub) {
        $result['val']['value'] = $sub['val'];
    }

Expr: Sum
    function Sum(&$result, $sub) {
        $result['val'] = $sub['val'];
    }

Start: (VariableAssignment | Expr) ";"?
    function VariableAssignment(&$result, $sub) {
        $name = $sub['val']['name'];
        $value = $sub['val']['value'];
        if (array_key_exists($name, $this->constants)) {
             throw new \Exception("Can't assign value to constant '$name'");
        }
        $this->variables[$name] = $value;
        $result['val'] = $value;
    }
    function Expr(&$result, $sub) {
        $result['val'] = $sub['val'];
    }

*/

}

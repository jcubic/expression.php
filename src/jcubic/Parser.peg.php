<?php
/*
 * This is part of jcubic/expression package
 * Copyright (c) 2024 Jakub T. Jankiewicz <https://jcu.bi>
 * Released under MIT license
 *
 * This is Parser PEG grammar
 */

namespace jcubic;

use hafriedlander\Peg;
use ReflectionFunction;
use Exception;

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
    public function is_typed($value) {
        if (!is_array($value)) {
            return false;
        }
        return array_keys($value) == ['type', 'value'];
    }
    private function with_type($value, $type = null) {
        if ($this->is_typed($value)) {
            return $value;
        }
        return ['type' => is_string($type) ? $type : gettype($value), 'value' => $value];
    }
    private function is_type($type, $value) {
        return $this->is_typed($value) && $value['type'] == $type;
    }
    private function is_regex($value) {
        return $this->is_type('regex', $value);
    }
    private function is_number($value) {
        return $this->is_type('double', $value) || $this->is_type('integer', $value);
    }
    private function is_array($value) {
        return $this->is_type('array', $value);
    }
    private function is_string($value) {
        return $this->is_type('string', $value);
    }
    private function validate_number($operation, $object) {
        $this->validate_types(['double', 'integer'], $operation, $object);
    }
    private function validate_array($operation, $object) {
        $this->validate_types(['array'], $operation, $object);
    }
    private function validate_types($types, $operation, $object) {
        if (!is_array($object)) {
            throw new Exception("Internal error: Invalid object $object");
        }
        $type = $object['type'];
        if (array_search($type, $types) === false) {
            if (count($types) == 1) {
                $valid = $types[0];
            } else {
                $valid = 'any of ' . implode(', ', $types);
            }
            throw new Exception("Invalid operand to $operation operation expecting $valid got $type");
        }
    }
    private function maybe_regex($value) {
        if (!is_string($value)) {
            throw new \Exception("Internal Error: invalid value pass to maybe_regex");
        }
        if (preg_match("/^(\W)[^\\1]+\\1[imsxUXJ]*$/", $value)) {
            return $this->with_type($value, 'regex');
        }
        return $this->with_type($value, 'string');
    }
    private function check_equal(&$result, $object, $fn) {
        $a = $object['value'];
        $b = $result['val']['value'];
        if ($this->is_array($object) || $this->is_array($result['val'])) {
            $result['val'] = $this->with_type($fn(json_encode($a), json_encode($b)));
        } else {
            $result['val'] = $this->with_type($fn($a, $b));
        }
    }
    private function compare(&$result, $object, $operation, $fn) {
       $this->validate_types(['integer', 'double', 'boolean'], $operation, $object);
       $this->validate_types(['integer', 'double', 'boolean'], $operation, $result['val']);
       $result['val'] = $this->with_type($fn($result['val']['value'], $object['value']));
    }
    private function is_eval_enabled() {
        // ref: https://stackoverflow.com/a/25158401/387194
        $disabled = explode(',', ini_get('disable_functions'));
        return !in_array('eval', $disabled);
    }
    private function _eval($code) {
        if (!$this->is_eval_enabled()) {
            // ref: https://stackoverflow.com/a/52689881/387194
            $tmp_file = tempnam(sys_get_temp_dir(), 'ccf');
            file_put_contents($tmp_file, '<?php $code ');
            $function = include($tmp_file);
            unlink($tmp_file);
            return $function;
        }
        return eval($code);
    }
    private function make_function($spec) {
        $name = $spec['name'];
        $params = $spec['params'];
        $body = $spec['body'];
        $code = 'return function(';
        $indexed_params = [];
        foreach ($params as $index => $param) {
            array_push($indexed_params, '$a' . $index);
        }
        $code .= implode(', ', $indexed_params);
        $code .= ') {
           $args = func_get_args();
           $params = ' . json_encode($params) . ';
           $expr = new jcubic\Expression();
           for ($i = 0; $i < count($params); ++$i) {
              $expr->variables[$params[$i]] = $args[$i];
           }
           return $expr->evaluate("' . addslashes($body) . '");
        };';
        $this->functions[$name] = $this->_eval($code);
    }
    private function shift($operation, $left, $right, $fn) {
        $this->validate_number($operation, $left);
        $this->validate_number($operation, $right);
        return $this->with_type($fn($left['value'], $right['value']));
    }

/*!* Expressions

Name: (/[A-Za-z_]/ /[A-Za-z_0-9]/* | '$' /[0-9A-Za-z_]+/)
Variable: Name
   function Name(&$result, $sub) {
       $result['val'] = $sub['text'];
   }

VariableReference: Variable
    function Variable(&$result, $sub) {
        $name = $sub['val'];
        if (array_key_exists($name, $this->constants)) {
            $result['val'] = $this->with_type($this->constants[$name]);
        } else if (array_key_exists($name, $this->variables)) {
            $result['val'] = $this->with_type($this->variables[$name]);
        } else {
            throw new Exception("Variable '$name' not found");
        }
    }

SingleQuoted: /'[^'\\]*(?:\\[\S\s][^'\\]*)*'/
DoubleQuoted: /"[^"\\]*(?:\\[\S\s][^"\\]*)*"/
String: SingleQuoted | DoubleQuoted
    function SingleQuoted(&$result, $sub) {
         $value = $sub['text'];
         $result['val'] = trim(stripslashes($value), "'");
    }
    function DoubleQuoted(&$result, $sub) {
         $value = $sub['text'];
         $result['val'] = trim(stripslashes($value), '"');
    }

Hex: '0x' /[0-9A-Fa-f]+/
Binary: '0b' /[01]+/
Decimal: /[0-9]+/
Float: /[0-9.]+e[0-9]+|[0-9]+(?:\.[0-9]*)?|\.[0-9]+/
Number: Hex | Binary | Float | Decimal
    function Hex(&$result, $sub) {
        $value = hexdec($sub['text']);
        $result['val'] = $this->with_type($value);
    }
    function Binary(&$result, $sub) {
        $value = bindec($sub['text']);
        $result['val'] = $this->with_type($value);
    }
    function Decimal(&$result, $sub) {
        $value = intval($sub['text']);
        $result['val'] = $this->with_type($value);
    }
    function Float(&$result, $sub) {
        $value = floatval($sub['text']);
        $result['val'] = $this->with_type($value);
    }

Consts: 'true' | 'false' | 'null'
RegExp: /(?<!\\\\)\/(?:[^\/]|\\\\\/)+\// /[imsxUXJ]/*
SimpleValue: Consts | RegExp | String | Number
    function Consts(&$result, $sub) {
        $result['val'] = $this->with_type(json_decode($sub['text']));
    }
    function RegExp(&$result, $sub) {
        $result['val'] = $this->with_type($sub['text'], 'regex');
    }
    function String(&$result, $sub) {
        $result['val'] = $this->maybe_regex($sub['val']);
    }
    function Number(&$result, $sub) {
        $result['val'] = $sub['val'];
    }

JSON: /([\[{](?>"(?:[^"]|\\")*"|[^[{\]}]|(?1))*[\]}])/
Value: JSON > | SimpleValue > | FunctionCall > | VariableReference > | '(' > Expr > ')' >
    function JSON(&$result, $sub) {
        $result['val'] = $this->with_type(json_decode($sub['text'], true));
    }
    function SimpleValue(&$result, $sub) {
        $result['val'] = $sub['val'];
    }
    function FunctionCall(&$result, $sub) {
        $result['val'] = $sub['val'];
    }
    function VariableReference(&$result, $sub) {
        $result['val'] = $sub['val'];
    }
    function Expr(&$result, $sub ) {
        $result['val'] = $sub['val'];
    }

Call: Name '(' > ( > Expr > ','? > ) * > ')' >
    function Name(&$result, $sub) {
        $name = $sub['text'];
        $result['val'] = [
            'args' => [],
            'name' => $name
        ];
    }
    function Expr(&$result, $sub) {
        array_push($result['val']['args'], $sub['val']['value']);
    }

FunctionCall: Call
    function Call(&$result, $sub) {
        $name = $sub['val']['name'];
        $name = preg_replace('/^arc/', 'a', $name);
        $is_builtin = in_array($name, $this->builtin_functions);
        $is_custom = array_key_exists($name, $this->functions);
        if (!$is_builtin && !$is_custom) {
            throw new Exception("function '$name' doesn't exists");
        }
        $args = $sub['val']['args'];
        $args_count = count($args);
        if ($is_builtin && $name == 'ln') {
            $name = 'log';
        }
        $function = new ReflectionFunction($is_builtin ? $name : $this->functions[$name]);
        $params_require_count = $function->getNumberOfRequiredParameters();
        $params_all_count = $function->getNumberOfParameters();
        if ($args_count < $params_require_count && $args_count > $params_all_count) {
            throw new Exception("Function '$name' expected $params_count params got $args_count");
        }
        $result['val'] = $this->with_type($function->invokeArgs($args));
    }

PowerOp: op:('^' | '**') > operand:Value >
Power: Value > PowerOp *
    function Value(&$result, $sub) {
        $result['val'] = $sub['val'];
    }
    function PowerOp(&$result, $sub) {
        $object = $sub['operand']['val'];
        $op = $sub['op']['text'];
        $this->validate_number($op, $object);
        $this->validate_number($op, $result['val']);
        $result['val'] = $this->with_type(pow($result['val']['value'],  $object['value']));
    }

UnaryMinus: '-' > operand:Power >
UnaryPlus: '+' > operand:Power >
Negation: '!' > operand:Power >
Unary: ( Negation | UnaryPlus | UnaryMinus | Power )
    function Power(&$result, $sub) {
        $result['val'] = $sub['val'];
    }
    function UnaryPlus(&$result, $sub) {
        $val = $sub['operand']['val'];
        if ($this->is_string($val)) {
            $val = floatval($val);
        }
        $result['val'] = $val;
    }
    function UnaryMinus(&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->validate_number('-', $object);
        $result['val'] = $this->with_type($object['value'] * -1);
    }
    function Negation(&$result, $sub) {
        $object = $sub['operand']['val'];
        $result['val'] = $this->with_type(!$object['value']);
    }

Times: '*' > operand:Unary >
Div: '/' > operand:Unary >
Mod: '%' > operand:Unary >
ImplicitTimes: operand:Power >
Property: '[' > operand:Expr > ']' >
Product: Unary > ( Times | Div | Mod | Property | ImplicitTimes ) *
    function Unary(&$result, $sub) {
        $result['val'] = $sub['val'];
    }
    function Power(&$result, $sub) {
        $result['val'] = $sub['val'];
    }
    function Times(&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->validate_number('*', $object);
        $this->validate_number('*', $result['val']);
        $result['val'] = $this->with_type($result['val']['value'] * $object['value']);
    }
    function ImplicitTimes(&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->validate_number('[*]', $object);
        $this->validate_number('[*]', $result['val']);
        $result['val'] = $this->with_type($result['val']['value'] * $object['value']);
    }
    function Div(&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->validate_number('/', $object);
        $this->validate_number('/', $result['val']);
        $result['val'] = $this->with_type($result['val']['value'] / $object['value']);
    }
    function Mod(&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->validate_number('%', $object);
        $this->validate_number('%', $result['val']);
        $result['val'] = $this->with_type($result['val']['value'] % $object['value']);
    }
    function Expr(&$result, $sub) {
        $result['val'] = $sub['val'];
    }
    function Property(&$result, $sub) {
        $prop = $sub['operand']['val'];
        $object = $result['val'];
        $this->validate_array('[', $object);
        $this->validate_types(['string', 'double', 'integer', 'boolean'], '[', $prop);
        $result['val'] = $this->with_type($object['value'][$prop['value']]);
    }

Plus: '+' > operand:Product >
Minus: '-' > operand:Product >
Sum: Product > ( Plus | Minus ) *
    function Product(&$result, $sub) {
        $result['val'] = $sub['val'];
    }
    function Plus(&$result, $sub) {
        $object = $sub['operand']['val'];
        if ($this->is_string($object)) {
            $result['val'] = $this->with_type($result['val']['value'] . $object['value']);
        } else {
            $this->validate_number('+', $object);
            $this->validate_number('+', $result['val']);
            $result['val'] = $this->with_type($result['val']['value'] + $object['value']);
        }
    }
    function Minus(&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->validate_number('-', $object);
        $this->validate_number('-', $result['val']);
        $result['val'] = $this->with_type($result['val']['value'] - $object['value']);
    }

VariableAssignment: Variable > '=' > Expr
    function Variable(&$result, $sub) {
        $result['val'] = ['name' => $sub['val']];
    }
    function Expr(&$result, $sub) {
        $result['val']['value'] = $sub['val'];
    }

FunctionBody: /.+/
FunctionAssignment: Name '(' > ( > Variable > ','? > ) * ')' > '=' !/[=~]/ > FunctionBody
   function Name(&$result, $sub) {
        $name = $sub['text'];
        $result['val'] = [
            'params' => [],
            'name' => $name,
            'body' => null
        ];
    }
    function Variable(&$result, $sub) {
        array_push($result['val']['params'], $sub['val']);
    }
    function FunctionBody(&$result, $sub) {
       $result['val']['body'] = $sub['text'];
    }

ShiftLeft: '<<' > operand:Sum >
ShiftRight: '>>' > operand:Sum >
BitShift: Sum > (ShiftRight | ShiftLeft) *
    function Sum(&$result, $sub) {
        $result['val'] = $sub['val'];
    }
    function ShiftLeft(&$result, $sub) {
        $result['val'] = $this->shift('<<', $result['val'], $sub['operand']['val'], function($a, $b) {
            return $a << $b;
        });
    }
    function ShiftRight(&$result, $sub) {
        $result['val'] = $this->shift('>>', $result['val'], $sub['operand']['val'], function($a, $b) {
            return $a >> $b;
        });
    }

StrictEqual: '===' > operand:BitShift >
StrictNotEqual: '!==' > operand:BitShift >
Equal: '==' > operand:BitShift >
Match: '=~' > operand:BitShift >
NotEqual: '!=' > operand:BitShift >
GreaterEqualThan: '>=' > operand:BitShift >
LessEqualThan: '<=' > operand:BitShift >
GreaterThan: '>' > operand:BitShift >
LessThan: '<' > operand:BitShift >
Compare: BitShift > (StrictEqual | Equal | Match | StrictNotEqual | NotEqual | GreaterEqualThan | GreaterThan | LessEqualThan | LessThan ) *
    function BitShift(&$result, $sub) {
        $result['val'] = $sub['val'];
    }
    function StrictEqual(&$result, $sub) {
        $this->check_equal($result, $sub['operand']['val'], function($a, $b) {
            return $a === $b;
        });
    }
    function Equal(&$result, $sub) {
        $this->check_equal($result, $sub['operand']['val'], function($a, $b) {
            return $a == $b;
        });
    }
    function Match(&$result, $sub) {
        $re = $sub['operand']['val'];
        $string = $result['val'];
        $this->validate_types(['string'], '=~', $string);
        $this->validate_types(['regex'], '=~', $re);
        $value = @preg_match($re['value'], $string['value'], $match);
        if (!is_int($value)) {
            $re = $re['value'];
            throw new Exception("Invalid regular expression: $re");
        }
        foreach (array_keys($this->variables) as $name) {
            unset($this->variables[$name]);
        }
        for ($i = 0; $i < count($match); $i++) {
            $this->variables['$' . $i] = $match[$i];
        }
        $result['val'] = $this->with_type($value == 1);
    }
    function StrictNotEqual(&$result, $sub) {
        $this->check_equal($result, $sub['operand']['val'], function($a, $b) {
            return $a !== $b;
        });
    }
    function NotEqual(&$result, $sub) {
        $this->check_equal($result, $sub['operand']['val'], function($a, $b) {
            return $a != $b;
        });
    }
    function GreaterEqualThan(&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->compare($result, $object, '>=', function($a, $b) {
            return $a >= $b;
        });
    }
    function LessEqualThan(&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->compare($result, $object, '>=', function($a, $b) {
            return $a <= $b;
        });
    }
    function GreaterThan(&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->compare($result, $object, '>=', function($a, $b) {
            return $a > $b;
        });
    }
    function LessThan(&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->compare($result, $object, '>=', function($a, $b) {
            return $a < $b;
        });
    }

And: '&&' > operand:Compare >
Or: '||' > operand:Compare >
Boolean: Compare > (And | Or ) *
    function Compare(&$result, $sub) {
        $result['val'] = $sub['val'];
    }
    function And(&$result, $sub) {
       $a = $result['val'];
       $b = $sub['operand']['val'];
       $result['val'] = $this->with_type($a['value'] ? $b['value'] : $a['value']);
    }
    function Or(&$result, $sub) {
       $a = $result['val'];
       $b = $sub['operand']['val'];
       $result['val'] = $this->with_type($a['value'] ? $a['value'] : $b['value']);
    }

Expr: Boolean
    function Boolean(&$result, $sub) {
        $result['val'] = $sub['val'];
    }

Start: (VariableAssignment | FunctionAssignment | Expr ) ';'?
    function VariableAssignment(&$result, $sub) {
        $name = $sub['val']['name'];
        $value = $sub['val']['value'];
        if (array_key_exists($name, $this->constants)) {
             throw new Exception("Can't assign value to constant '$name'");
        }
        $this->variables[$name] = $value;
        $result['val'] = $value;
    }
    function FunctionAssignment(&$result, $sub) {
        $this->make_function($sub['val']);
        $result['val'] = true;
    }
    function Expr(&$result, $sub) {
        $result['val'] = $sub['val'];
    }

*/

}

<?php
namespace jcubic;

use hafriedlander\Peg;
use ReflectionFunction;

class Parser extends Peg\Parser\Basic {
  public $variables;
  public $functions;
  private $constants;
  public function __construct($expr, &$variables, &$constants, &$functions) {
      parent::__construct($expr);
      $this->variables = $variables;
      $this->constants = $constants;
      $this->functions = $functions;
  }

/*!* Expressions
Name: /[A-Za-z]+/
    function Name(&$result, $sub) {
        $result['val'] = $sub['text'];
    }

Number: /[0-9]+/
Value: Name > | Number > | '(' > Expr > ')' >
    function Number(&$result, $sub ) {
        $result['val'] = $sub['text'];
    }
    function Expr(&$result, $sub ) {
        $result['val'] = $sub['val'];
    }
    function Name(&$result, $sub) {
        $name = $sub['text'];
        if (array_key_exists($name, $this->constants)) {
            $result['val'] = $this->constants[$name];
        } else if (array_key_exists($name, $this->variables)) {
            $result['val'] = $this->variables[$name];
        } else {
            throw new \Exception("Variable '$name' not found");
        }
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
       if (!array_key_exists($name, $this->functions)) {
           throw new \Exception("function '$name' doesn't exists");
       }
       $function = new ReflectionFunction($this->functions[$name]);
       $params_count = $function->getNumberOfParameters();
       $args = $sub['val']['args'];
       $args_count = count($args);
       if ($params_count != $args_count) {
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
Product: Unnary > ( Times | Div ) *
    function Unnary( &$result, $sub ) {
        $result['val'] = $sub['val'];
    }
    function Times( &$result, $sub ) {
        $result['val'] *= $sub['operand']['val'];
    }
    function Div( &$result, $sub ) {
        $result['val'] /= $sub['operand']['val'];
    }

Plus: '+' > operand:Product >
Minus: '-' > operand:Product >
Sum: Product > ( Plus | Minus ) *
    function Product( &$result, $sub ) {
        $result['val'] = $sub['val'];
    }
    function Plus( &$result, $sub ) {
        $result['val'] += $sub['operand']['val'];
    }
    function Minus( &$result, $sub ) {
        $result['val'] -= $sub['operand']['val'];
    }

Variable: Name > "=" > Expr
    function Name(&$result, $sub) {
        $result['val'] = ["name" => $sub['text']];
    }
    function Expr( &$result, $sub ) {
        $result['val']['value'] = $sub['val'];
    }

Expr: Sum
    function Sum( &$result, $sub ) {
        $result['val'] = $sub['val'];
    }

Start: Variable | Expr
    function Variable(&$result, $sub) {
        $name = $sub['val']['name'];
        $value = $sub['val']['value'];
        if (array_key_exists($name, $this->constants)) {
             throw new \Exception("Can't assign value to constant '$name'");
        }
        $this->variables[$name] = $value;
        $result['val'] = true;
    }
    function Expr( &$result, $sub ) {
        $result['val'] = $sub['val'];
    }

*/

}

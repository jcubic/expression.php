<?php
namespace jcubic;

use hafriedlander\Peg;

class Parser extends Peg\Parser\Basic {
  public $variables;

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
        if (!array_key_exists($name, $this->variables)) {
            throw new \Exception("variable $name not found");
        }
        $result['val'] = $this->variables[$name];
    }

Negation: '-' > operand:Value >
ToInt: '+' > operand:Value >
Unnary: (Negation | ToInt | Value)
   function ToInt( &$result, $sub ) {
        $val = $sub['operand']['val'];
        if (is_string($val)) {
            $val = floatval($val);
        }
        $result['val'] = $val;
   }
   function Value( &$result, $sub ) {
        $result['val'] = $sub['val'];
    }
   function Negation( &$result, $sub ) {
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
        $this->variables[$name] = $value;
        $result['val'] = true;
    }
    function Expr( &$result, $sub ) {
        $result['val'] = $sub['val'];
    }

*/

}

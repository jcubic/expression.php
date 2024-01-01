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

Times: '*' > operand:Value >
Div: '/' > operand:Value >
Product: Value > ( Times | Div ) *
    function Value( &$result, $sub ) {
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

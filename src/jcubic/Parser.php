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

/* Consts: "true" | "false" | "null" */
protected $match_Consts_typestack = ['Consts'];
function match_Consts($stack = []) {
	$matchrule = 'Consts';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_7 = \null;
	do {
		$res_0 = $result;
		$pos_0 = $this->pos;
		if (($subres = $this->literal('true')) !== \false) {
			$result["text"] .= $subres;
			$_7 = \true; break;
		}
		$result = $res_0;
		$this->setPos($pos_0);
		$_5 = \null;
		do {
			$res_2 = $result;
			$pos_2 = $this->pos;
			if (($subres = $this->literal('false')) !== \false) {
				$result["text"] .= $subres;
				$_5 = \true; break;
			}
			$result = $res_2;
			$this->setPos($pos_2);
			if (($subres = $this->literal('null')) !== \false) {
				$result["text"] .= $subres;
				$_5 = \true; break;
			}
			$result = $res_2;
			$this->setPos($pos_2);
			$_5 = \false; break;
		}
		while(\false);
		if($_5 === \true) { $_7 = \true; break; }
		$result = $res_0;
		$this->setPos($pos_0);
		$_7 = \false; break;
	}
	while(\false);
	if($_7 === \true) { return $this->finalise($result); }
	if($_7 === \false) { return \false; }
}


/* Name: "$"? /[A-Za-z]+/ */
protected $match_Name_typestack = ['Name'];
function match_Name($stack = []) {
	$matchrule = 'Name';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_11 = \null;
	do {
		$res_9 = $result;
		$pos_9 = $this->pos;
		if (\substr($this->string, $this->pos, 1) === '$') {
			$this->addPos(1);
			$result["text"] .= '$';
		}
		else {
			$result = $res_9;
			$this->setPos($pos_9);
			unset($res_9, $pos_9);
		}
		if (($subres = $this->rx('/[A-Za-z]+/')) !== \false) { $result["text"] .= $subres; }
		else { $_11 = \false; break; }
		$_11 = \true; break;
	}
	while(\false);
	if($_11 === \true) { return $this->finalise($result); }
	if($_11 === \false) { return \false; }
}


/* Number: /[0-9.]+e[0-9]+|[0-9]+(?:\.[0-9]*)?|\.[0-9]+/ */
protected $match_Number_typestack = ['Number'];
function match_Number($stack = []) {
	$matchrule = 'Number';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/[0-9.]+e[0-9]+|[0-9]+(?:\.[0-9]*)?|\.[0-9]+/')) !== \false) {
		$result["text"] .= $subres;
		return $this->finalise($result);
	}
	else { return \false; }
}


/* Value: Consts > | Name > | Number > | '(' > Expr > ')' > */
protected $match_Value_typestack = ['Value'];
function match_Value($stack = []) {
	$matchrule = 'Value';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_41 = \null;
	do {
		$res_14 = $result;
		$pos_14 = $this->pos;
		$_17 = \null;
		do {
			$key = 'match_'.'Consts'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else { $_17 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$_17 = \true; break;
		}
		while(\false);
		if($_17 === \true) { $_41 = \true; break; }
		$result = $res_14;
		$this->setPos($pos_14);
		$_39 = \null;
		do {
			$res_19 = $result;
			$pos_19 = $this->pos;
			$_22 = \null;
			do {
				$key = 'match_'.'Name'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_22 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_22 = \true; break;
			}
			while(\false);
			if($_22 === \true) { $_39 = \true; break; }
			$result = $res_19;
			$this->setPos($pos_19);
			$_37 = \null;
			do {
				$res_24 = $result;
				$pos_24 = $this->pos;
				$_27 = \null;
				do {
					$key = 'match_'.'Number'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) { $this->store($result, $subres); }
					else { $_27 = \false; break; }
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					$_27 = \true; break;
				}
				while(\false);
				if($_27 === \true) { $_37 = \true; break; }
				$result = $res_24;
				$this->setPos($pos_24);
				$_35 = \null;
				do {
					if (\substr($this->string, $this->pos, 1) === '(') {
						$this->addPos(1);
						$result["text"] .= '(';
					}
					else { $_35 = \false; break; }
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					$key = 'match_'.'Expr'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) { $this->store($result, $subres); }
					else { $_35 = \false; break; }
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					if (\substr($this->string, $this->pos, 1) === ')') {
						$this->addPos(1);
						$result["text"] .= ')';
					}
					else { $_35 = \false; break; }
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					$_35 = \true; break;
				}
				while(\false);
				if($_35 === \true) { $_37 = \true; break; }
				$result = $res_24;
				$this->setPos($pos_24);
				$_37 = \false; break;
			}
			while(\false);
			if($_37 === \true) { $_39 = \true; break; }
			$result = $res_19;
			$this->setPos($pos_19);
			$_39 = \false; break;
		}
		while(\false);
		if($_39 === \true) { $_41 = \true; break; }
		$result = $res_14;
		$this->setPos($pos_14);
		$_41 = \false; break;
	}
	while(\false);
	if($_41 === \true) { return $this->finalise($result); }
	if($_41 === \false) { return \false; }
}

public function Value_Consts (&$result, $sub) {
        $result['val'] = json_decode($sub['text']);
    }

public function Value_Number (&$result, $sub) {
        $result['val'] = floatval($sub['text']);
    }

public function Value_Expr (&$result, $sub ) {
        $result['val'] = $sub['val'];
    }

public function Value_Name (&$result, $sub) {
        $name = $sub['text'];
        if (array_key_exists($name, $this->constants)) {
            $result['val'] = $this->constants[$name];
        } else if (array_key_exists($name, $this->variables)) {
            $result['val'] = $this->variables[$name];
        } else {
            throw new \Exception("Variable '$name' not found");
        }
    }

/* Call: Name "(" > ( > Expr > ","? > ) * > ")" > */
protected $match_Call_typestack = ['Call'];
function match_Call($stack = []) {
	$matchrule = 'Call';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_56 = \null;
	do {
		$key = 'match_'.'Name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_56 = \false; break; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_56 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_52 = $result;
			$pos_52 = $this->pos;
			$_51 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'Expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_51 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$res_49 = $result;
				$pos_49 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else {
					$result = $res_49;
					$this->setPos($pos_49);
					unset($res_49, $pos_49);
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_51 = \true; break;
			}
			while(\false);
			if($_51 === \false) {
				$result = $res_52;
				$this->setPos($pos_52);
				unset($res_52, $pos_52);
				break;
			}
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_56 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_56 = \true; break;
	}
	while(\false);
	if($_56 === \true) { return $this->finalise($result); }
	if($_56 === \false) { return \false; }
}

public function Call_Name (&$result, $sub) {
       $name = $sub['text'];
       $result['val'] = [
           "args" => [],
           "name" => $name
       ];
   }

public function Call_Expr (&$result, $sub) {
       array_push($result['val']['args'], $sub['val']);
   }

/* Negation: '-' > operand:Value > */
protected $match_Negation_typestack = ['Negation'];
function match_Negation($stack = []) {
	$matchrule = 'Negation';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_62 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_62 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_62 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_62 = \true; break;
	}
	while(\false);
	if($_62 === \true) { return $this->finalise($result); }
	if($_62 === \false) { return \false; }
}


/* ToInt: '+' > operand:Value > */
protected $match_ToInt_typestack = ['ToInt'];
function match_ToInt($stack = []) {
	$matchrule = 'ToInt';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_68 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_68 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_68 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_68 = \true; break;
	}
	while(\false);
	if($_68 === \true) { return $this->finalise($result); }
	if($_68 === \false) { return \false; }
}


/* Unnary: (Call | Negation | ToInt | Value ) */
protected $match_Unnary_typestack = ['Unnary'];
function match_Unnary($stack = []) {
	$matchrule = 'Unnary';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_83 = \null;
	do {
		$_81 = \null;
		do {
			$res_70 = $result;
			$pos_70 = $this->pos;
			$key = 'match_'.'Call'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres);
				$_81 = \true; break;
			}
			$result = $res_70;
			$this->setPos($pos_70);
			$_79 = \null;
			do {
				$res_72 = $result;
				$pos_72 = $this->pos;
				$key = 'match_'.'Negation'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_79 = \true; break;
				}
				$result = $res_72;
				$this->setPos($pos_72);
				$_77 = \null;
				do {
					$res_74 = $result;
					$pos_74 = $this->pos;
					$key = 'match_'.'ToInt'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_77 = \true; break;
					}
					$result = $res_74;
					$this->setPos($pos_74);
					$key = 'match_'.'Value'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_77 = \true; break;
					}
					$result = $res_74;
					$this->setPos($pos_74);
					$_77 = \false; break;
				}
				while(\false);
				if($_77 === \true) { $_79 = \true; break; }
				$result = $res_72;
				$this->setPos($pos_72);
				$_79 = \false; break;
			}
			while(\false);
			if($_79 === \true) { $_81 = \true; break; }
			$result = $res_70;
			$this->setPos($pos_70);
			$_81 = \false; break;
		}
		while(\false);
		if($_81 === \false) { $_83 = \false; break; }
		$_83 = \true; break;
	}
	while(\false);
	if($_83 === \true) { return $this->finalise($result); }
	if($_83 === \false) { return \false; }
}

public function Unnary_ToInt (&$result, $sub) {
        $val = $sub['operand']['val'];
        if (is_string($val)) {
            $val = floatval($val);
        }
        $result['val'] = $val;
   }

public function Unnary_Call (&$result, $sub) {
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

public function Unnary_Value (&$result, $sub) {
       $result['val'] = $sub['val'];
   }

public function Unnary_Negation (&$result, $sub) {
       $result['val'] = $sub['operand']['val'] * -1;
   }

/* Times: '*' > operand:Unnary > */
protected $match_Times_typestack = ['Times'];
function match_Times($stack = []) {
	$matchrule = 'Times';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_89 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '*') {
			$this->addPos(1);
			$result["text"] .= '*';
		}
		else { $_89 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unnary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_89 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_89 = \true; break;
	}
	while(\false);
	if($_89 === \true) { return $this->finalise($result); }
	if($_89 === \false) { return \false; }
}


/* Div: '/' > operand:Unnary > */
protected $match_Div_typestack = ['Div'];
function match_Div($stack = []) {
	$matchrule = 'Div';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_95 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '/') {
			$this->addPos(1);
			$result["text"] .= '/';
		}
		else { $_95 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unnary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_95 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_95 = \true; break;
	}
	while(\false);
	if($_95 === \true) { return $this->finalise($result); }
	if($_95 === \false) { return \false; }
}


/* Mod: '%' > operand:Unnary > */
protected $match_Mod_typestack = ['Mod'];
function match_Mod($stack = []) {
	$matchrule = 'Mod';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_101 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '%') {
			$this->addPos(1);
			$result["text"] .= '%';
		}
		else { $_101 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unnary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_101 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_101 = \true; break;
	}
	while(\false);
	if($_101 === \true) { return $this->finalise($result); }
	if($_101 === \false) { return \false; }
}


/* Product: Unnary > ( Times | Div | Mod ) * */
protected $match_Product_typestack = ['Product'];
function match_Product($stack = []) {
	$matchrule = 'Product';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_116 = \null;
	do {
		$key = 'match_'.'Unnary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_116 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_115 = $result;
			$pos_115 = $this->pos;
			$_114 = \null;
			do {
				$_112 = \null;
				do {
					$res_105 = $result;
					$pos_105 = $this->pos;
					$key = 'match_'.'Times'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_112 = \true; break;
					}
					$result = $res_105;
					$this->setPos($pos_105);
					$_110 = \null;
					do {
						$res_107 = $result;
						$pos_107 = $this->pos;
						$key = 'match_'.'Div'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_110 = \true; break;
						}
						$result = $res_107;
						$this->setPos($pos_107);
						$key = 'match_'.'Mod'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_110 = \true; break;
						}
						$result = $res_107;
						$this->setPos($pos_107);
						$_110 = \false; break;
					}
					while(\false);
					if($_110 === \true) { $_112 = \true; break; }
					$result = $res_105;
					$this->setPos($pos_105);
					$_112 = \false; break;
				}
				while(\false);
				if($_112 === \false) { $_114 = \false; break; }
				$_114 = \true; break;
			}
			while(\false);
			if($_114 === \false) {
				$result = $res_115;
				$this->setPos($pos_115);
				unset($res_115, $pos_115);
				break;
			}
		}
		$_116 = \true; break;
	}
	while(\false);
	if($_116 === \true) { return $this->finalise($result); }
	if($_116 === \false) { return \false; }
}

public function Product_Unnary (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Product_Times (&$result, $sub) {
        $result['val'] *= $sub['operand']['val'];
    }

public function Product_Div (&$result, $sub) {
        $result['val'] /= $sub['operand']['val'];
    }

public function Product_Mod (&$result, $sub) {
        $result['val'] %= $sub['operand']['val'];
    }

/* Plus: '+' > operand:Product > */
protected $match_Plus_typestack = ['Plus'];
function match_Plus($stack = []) {
	$matchrule = 'Plus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_122 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_122 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_122 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_122 = \true; break;
	}
	while(\false);
	if($_122 === \true) { return $this->finalise($result); }
	if($_122 === \false) { return \false; }
}


/* Minus: '-' > operand:Product > */
protected $match_Minus_typestack = ['Minus'];
function match_Minus($stack = []) {
	$matchrule = 'Minus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_128 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_128 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_128 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_128 = \true; break;
	}
	while(\false);
	if($_128 === \true) { return $this->finalise($result); }
	if($_128 === \false) { return \false; }
}


/* Sum: Product > ( Plus | Minus ) * */
protected $match_Sum_typestack = ['Sum'];
function match_Sum($stack = []) {
	$matchrule = 'Sum';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_139 = \null;
	do {
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_139 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_138 = $result;
			$pos_138 = $this->pos;
			$_137 = \null;
			do {
				$_135 = \null;
				do {
					$res_132 = $result;
					$pos_132 = $this->pos;
					$key = 'match_'.'Plus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_135 = \true; break;
					}
					$result = $res_132;
					$this->setPos($pos_132);
					$key = 'match_'.'Minus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_135 = \true; break;
					}
					$result = $res_132;
					$this->setPos($pos_132);
					$_135 = \false; break;
				}
				while(\false);
				if($_135 === \false) { $_137 = \false; break; }
				$_137 = \true; break;
			}
			while(\false);
			if($_137 === \false) {
				$result = $res_138;
				$this->setPos($pos_138);
				unset($res_138, $pos_138);
				break;
			}
		}
		$_139 = \true; break;
	}
	while(\false);
	if($_139 === \true) { return $this->finalise($result); }
	if($_139 === \false) { return \false; }
}

public function Sum_Product (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Sum_Plus (&$result, $sub) {
        $result['val'] += $sub['operand']['val'];
    }

public function Sum_Minus (&$result, $sub) {
        $result['val'] -= $sub['operand']['val'];
    }

/* Variable: Name > "=" > Expr */
protected $match_Variable_typestack = ['Variable'];
function match_Variable($stack = []) {
	$matchrule = 'Variable';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_146 = \null;
	do {
		$key = 'match_'.'Name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_146 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '=') {
			$this->addPos(1);
			$result["text"] .= '=';
		}
		else { $_146 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_146 = \false; break; }
		$_146 = \true; break;
	}
	while(\false);
	if($_146 === \true) { return $this->finalise($result); }
	if($_146 === \false) { return \false; }
}

public function Variable_Name (&$result, $sub) {
        $result['val'] = ["name" => $sub['text']];
    }

public function Variable_Expr (&$result, $sub) {
        $result['val']['value'] = $sub['val'];
    }

/* Expr: Sum */
protected $match_Expr_typestack = ['Expr'];
function match_Expr($stack = []) {
	$matchrule = 'Expr';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$key = 'match_'.'Sum'; $pos = $this->pos;
	$subres = $this->packhas($key, $pos)
		? $this->packread($key, $pos)
		: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
	if ($subres !== \false) {
		$this->store($result, $subres);
		return $this->finalise($result);
	}
	else { return \false; }
}

public function Expr_Sum (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

/* Start: Variable | Expr */
protected $match_Start_typestack = ['Start'];
function match_Start($stack = []) {
	$matchrule = 'Start';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_152 = \null;
	do {
		$res_149 = $result;
		$pos_149 = $this->pos;
		$key = 'match_'.'Variable'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_152 = \true; break;
		}
		$result = $res_149;
		$this->setPos($pos_149);
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_152 = \true; break;
		}
		$result = $res_149;
		$this->setPos($pos_149);
		$_152 = \false; break;
	}
	while(\false);
	if($_152 === \true) { return $this->finalise($result); }
	if($_152 === \false) { return \false; }
}

public function Start_Variable (&$result, $sub) {
        $name = $sub['val']['name'];
        $value = $sub['val']['value'];
        if (array_key_exists($name, $this->constants)) {
             throw new \Exception("Can't assign value to constant '$name'");
        }
        $this->variables[$name] = $value;
        $result['val'] = $value;
    }

public function Start_Expr (&$result, $sub) {
        $result['val'] = $sub['val'];
    }



}

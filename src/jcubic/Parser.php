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

TODO: float numbers scientific notation
      regular expressions + string regex
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


/* Name: /[A-Za-z]+/ */
protected $match_Name_typestack = ['Name'];
function match_Name($stack = []) {
	$matchrule = 'Name';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/[A-Za-z]+/')) !== \false) {
		$result["text"] .= $subres;
		return $this->finalise($result);
	}
	else { return \false; }
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
	$_38 = \null;
	do {
		$res_11 = $result;
		$pos_11 = $this->pos;
		$_14 = \null;
		do {
			$key = 'match_'.'Consts'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else { $_14 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$_14 = \true; break;
		}
		while(\false);
		if($_14 === \true) { $_38 = \true; break; }
		$result = $res_11;
		$this->setPos($pos_11);
		$_36 = \null;
		do {
			$res_16 = $result;
			$pos_16 = $this->pos;
			$_19 = \null;
			do {
				$key = 'match_'.'Name'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_19 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_19 = \true; break;
			}
			while(\false);
			if($_19 === \true) { $_36 = \true; break; }
			$result = $res_16;
			$this->setPos($pos_16);
			$_34 = \null;
			do {
				$res_21 = $result;
				$pos_21 = $this->pos;
				$_24 = \null;
				do {
					$key = 'match_'.'Number'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) { $this->store($result, $subres); }
					else { $_24 = \false; break; }
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					$_24 = \true; break;
				}
				while(\false);
				if($_24 === \true) { $_34 = \true; break; }
				$result = $res_21;
				$this->setPos($pos_21);
				$_32 = \null;
				do {
					if (\substr($this->string, $this->pos, 1) === '(') {
						$this->addPos(1);
						$result["text"] .= '(';
					}
					else { $_32 = \false; break; }
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					$key = 'match_'.'Expr'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) { $this->store($result, $subres); }
					else { $_32 = \false; break; }
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					if (\substr($this->string, $this->pos, 1) === ')') {
						$this->addPos(1);
						$result["text"] .= ')';
					}
					else { $_32 = \false; break; }
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					$_32 = \true; break;
				}
				while(\false);
				if($_32 === \true) { $_34 = \true; break; }
				$result = $res_21;
				$this->setPos($pos_21);
				$_34 = \false; break;
			}
			while(\false);
			if($_34 === \true) { $_36 = \true; break; }
			$result = $res_16;
			$this->setPos($pos_16);
			$_36 = \false; break;
		}
		while(\false);
		if($_36 === \true) { $_38 = \true; break; }
		$result = $res_11;
		$this->setPos($pos_11);
		$_38 = \false; break;
	}
	while(\false);
	if($_38 === \true) { return $this->finalise($result); }
	if($_38 === \false) { return \false; }
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
	$_53 = \null;
	do {
		$key = 'match_'.'Name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_53 = \false; break; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_53 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_49 = $result;
			$pos_49 = $this->pos;
			$_48 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'Expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_48 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$res_46 = $result;
				$pos_46 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else {
					$result = $res_46;
					$this->setPos($pos_46);
					unset($res_46, $pos_46);
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_48 = \true; break;
			}
			while(\false);
			if($_48 === \false) {
				$result = $res_49;
				$this->setPos($pos_49);
				unset($res_49, $pos_49);
				break;
			}
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_53 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_53 = \true; break;
	}
	while(\false);
	if($_53 === \true) { return $this->finalise($result); }
	if($_53 === \false) { return \false; }
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
	$_59 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_59 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_59 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_59 = \true; break;
	}
	while(\false);
	if($_59 === \true) { return $this->finalise($result); }
	if($_59 === \false) { return \false; }
}


/* ToInt: '+' > operand:Value > */
protected $match_ToInt_typestack = ['ToInt'];
function match_ToInt($stack = []) {
	$matchrule = 'ToInt';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_65 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_65 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_65 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_65 = \true; break;
	}
	while(\false);
	if($_65 === \true) { return $this->finalise($result); }
	if($_65 === \false) { return \false; }
}


/* Unnary: (Call | Negation | ToInt | Value ) */
protected $match_Unnary_typestack = ['Unnary'];
function match_Unnary($stack = []) {
	$matchrule = 'Unnary';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_80 = \null;
	do {
		$_78 = \null;
		do {
			$res_67 = $result;
			$pos_67 = $this->pos;
			$key = 'match_'.'Call'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres);
				$_78 = \true; break;
			}
			$result = $res_67;
			$this->setPos($pos_67);
			$_76 = \null;
			do {
				$res_69 = $result;
				$pos_69 = $this->pos;
				$key = 'match_'.'Negation'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_76 = \true; break;
				}
				$result = $res_69;
				$this->setPos($pos_69);
				$_74 = \null;
				do {
					$res_71 = $result;
					$pos_71 = $this->pos;
					$key = 'match_'.'ToInt'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_74 = \true; break;
					}
					$result = $res_71;
					$this->setPos($pos_71);
					$key = 'match_'.'Value'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_74 = \true; break;
					}
					$result = $res_71;
					$this->setPos($pos_71);
					$_74 = \false; break;
				}
				while(\false);
				if($_74 === \true) { $_76 = \true; break; }
				$result = $res_69;
				$this->setPos($pos_69);
				$_76 = \false; break;
			}
			while(\false);
			if($_76 === \true) { $_78 = \true; break; }
			$result = $res_67;
			$this->setPos($pos_67);
			$_78 = \false; break;
		}
		while(\false);
		if($_78 === \false) { $_80 = \false; break; }
		$_80 = \true; break;
	}
	while(\false);
	if($_80 === \true) { return $this->finalise($result); }
	if($_80 === \false) { return \false; }
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
	$_86 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '*') {
			$this->addPos(1);
			$result["text"] .= '*';
		}
		else { $_86 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unnary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_86 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_86 = \true; break;
	}
	while(\false);
	if($_86 === \true) { return $this->finalise($result); }
	if($_86 === \false) { return \false; }
}


/* Div: '/' > operand:Unnary > */
protected $match_Div_typestack = ['Div'];
function match_Div($stack = []) {
	$matchrule = 'Div';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_92 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '/') {
			$this->addPos(1);
			$result["text"] .= '/';
		}
		else { $_92 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unnary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_92 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_92 = \true; break;
	}
	while(\false);
	if($_92 === \true) { return $this->finalise($result); }
	if($_92 === \false) { return \false; }
}


/* Mod: '%' > operand:Unnary > */
protected $match_Mod_typestack = ['Mod'];
function match_Mod($stack = []) {
	$matchrule = 'Mod';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_98 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '%') {
			$this->addPos(1);
			$result["text"] .= '%';
		}
		else { $_98 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unnary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_98 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_98 = \true; break;
	}
	while(\false);
	if($_98 === \true) { return $this->finalise($result); }
	if($_98 === \false) { return \false; }
}


/* Product: Unnary > ( Times | Div | Mod ) * */
protected $match_Product_typestack = ['Product'];
function match_Product($stack = []) {
	$matchrule = 'Product';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_113 = \null;
	do {
		$key = 'match_'.'Unnary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_113 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_112 = $result;
			$pos_112 = $this->pos;
			$_111 = \null;
			do {
				$_109 = \null;
				do {
					$res_102 = $result;
					$pos_102 = $this->pos;
					$key = 'match_'.'Times'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_109 = \true; break;
					}
					$result = $res_102;
					$this->setPos($pos_102);
					$_107 = \null;
					do {
						$res_104 = $result;
						$pos_104 = $this->pos;
						$key = 'match_'.'Div'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_107 = \true; break;
						}
						$result = $res_104;
						$this->setPos($pos_104);
						$key = 'match_'.'Mod'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_107 = \true; break;
						}
						$result = $res_104;
						$this->setPos($pos_104);
						$_107 = \false; break;
					}
					while(\false);
					if($_107 === \true) { $_109 = \true; break; }
					$result = $res_102;
					$this->setPos($pos_102);
					$_109 = \false; break;
				}
				while(\false);
				if($_109 === \false) { $_111 = \false; break; }
				$_111 = \true; break;
			}
			while(\false);
			if($_111 === \false) {
				$result = $res_112;
				$this->setPos($pos_112);
				unset($res_112, $pos_112);
				break;
			}
		}
		$_113 = \true; break;
	}
	while(\false);
	if($_113 === \true) { return $this->finalise($result); }
	if($_113 === \false) { return \false; }
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
	$_119 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_119 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_119 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_119 = \true; break;
	}
	while(\false);
	if($_119 === \true) { return $this->finalise($result); }
	if($_119 === \false) { return \false; }
}


/* Minus: '-' > operand:Product > */
protected $match_Minus_typestack = ['Minus'];
function match_Minus($stack = []) {
	$matchrule = 'Minus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_125 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_125 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_125 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_125 = \true; break;
	}
	while(\false);
	if($_125 === \true) { return $this->finalise($result); }
	if($_125 === \false) { return \false; }
}


/* Sum: Product > ( Plus | Minus ) * */
protected $match_Sum_typestack = ['Sum'];
function match_Sum($stack = []) {
	$matchrule = 'Sum';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_136 = \null;
	do {
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_136 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_135 = $result;
			$pos_135 = $this->pos;
			$_134 = \null;
			do {
				$_132 = \null;
				do {
					$res_129 = $result;
					$pos_129 = $this->pos;
					$key = 'match_'.'Plus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_132 = \true; break;
					}
					$result = $res_129;
					$this->setPos($pos_129);
					$key = 'match_'.'Minus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_132 = \true; break;
					}
					$result = $res_129;
					$this->setPos($pos_129);
					$_132 = \false; break;
				}
				while(\false);
				if($_132 === \false) { $_134 = \false; break; }
				$_134 = \true; break;
			}
			while(\false);
			if($_134 === \false) {
				$result = $res_135;
				$this->setPos($pos_135);
				unset($res_135, $pos_135);
				break;
			}
		}
		$_136 = \true; break;
	}
	while(\false);
	if($_136 === \true) { return $this->finalise($result); }
	if($_136 === \false) { return \false; }
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
	$_143 = \null;
	do {
		$key = 'match_'.'Name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_143 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '=') {
			$this->addPos(1);
			$result["text"] .= '=';
		}
		else { $_143 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_143 = \false; break; }
		$_143 = \true; break;
	}
	while(\false);
	if($_143 === \true) { return $this->finalise($result); }
	if($_143 === \false) { return \false; }
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
	$_149 = \null;
	do {
		$res_146 = $result;
		$pos_146 = $this->pos;
		$key = 'match_'.'Variable'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_149 = \true; break;
		}
		$result = $res_146;
		$this->setPos($pos_146);
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_149 = \true; break;
		}
		$result = $res_146;
		$this->setPos($pos_146);
		$_149 = \false; break;
	}
	while(\false);
	if($_149 === \true) { return $this->finalise($result); }
	if($_149 === \false) { return \false; }
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

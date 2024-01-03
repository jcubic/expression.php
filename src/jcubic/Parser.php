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
use Exception;

/*

TODO: JSON objects / JSON comparison == !=
      boolean comparators == != < > <= >=
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
    public function is_typed($value) {
        if (!is_array($value)) {
            return false;
        }
        return array_keys($value) == ["type", "value"];
    }
    private function with_type($value, $type = null) {
        if ($this->is_typed($value)) {
            return $value;
        }
        return ["type" => is_string($type) ? $type : gettype($value), "value" => $value];
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
    private function is_string($value) {
        return $this->is_type('string', $value);
    }
    private function validate_number($operation, $object) {
        $this->validate_types(['double', 'integer'], $operation, $object);
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

/* SimpleRegExp: /(?<!\\\\)\/(?:[^\/]|\\\\\/)+\// /[imsxUXJ]/* */
protected $match_SimpleRegExp_typestack = ['SimpleRegExp'];
function match_SimpleRegExp($stack = []) {
	$matchrule = 'SimpleRegExp';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_2 = \null;
	do {
		if (($subres = $this->rx('/(?<!\\\\\\\\)\/(?:[^\/]|\\\\\\\\\/)+\//')) !== \false) { $result["text"] .= $subres; }
		else { $_2 = \false; break; }
		while (\true) {
			$res_1 = $result;
			$pos_1 = $this->pos;
			if (($subres = $this->rx('/[imsxUXJ]/')) !== \false) { $result["text"] .= $subres; }
			else {
				$result = $res_1;
				$this->setPos($pos_1);
				unset($res_1, $pos_1);
				break;
			}
		}
		$_2 = \true; break;
	}
	while(\false);
	if($_2 === \true) { return $this->finalise($result); }
	if($_2 === \false) { return \false; }
}


/* StringRegExp: q:/['"]/ mark:/\w/ string '$mark' /[imsxUXJ]/* '$q' */
protected $match_StringRegExp_typestack = ['StringRegExp'];
function match_StringRegExp($stack = []) {
	$matchrule = 'StringRegExp';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_10 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "q");
		if (($subres = $this->rx('/[\'"]/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'q');
		}
		else {
			$result = \array_pop($stack);
			$_10 = \false; break;
		}
		$stack[] = $result; $result = $this->construct($matchrule, "mark");
		if (($subres = $this->rx('/\w/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'mark');
		}
		else {
			$result = \array_pop($stack);
			$_10 = \false; break;
		}
		$key = 'match_'.'string'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_10 = \false; break; }
		if (($subres = $this->literal(''.$this->expression($result, $stack, 'mark').'')) !== \false) { $result["text"] .= $subres; }
		else { $_10 = \false; break; }
		while (\true) {
			$res_8 = $result;
			$pos_8 = $this->pos;
			if (($subres = $this->rx('/[imsxUXJ]/')) !== \false) { $result["text"] .= $subres; }
			else {
				$result = $res_8;
				$this->setPos($pos_8);
				unset($res_8, $pos_8);
				break;
			}
		}
		if (($subres = $this->literal(''.$this->expression($result, $stack, 'q').'')) !== \false) { $result["text"] .= $subres; }
		else { $_10 = \false; break; }
		$_10 = \true; break;
	}
	while(\false);
	if($_10 === \true) { return $this->finalise($result); }
	if($_10 === \false) { return \false; }
}


/* RegExp: SimpleRegExp | StringRegExp */
protected $match_RegExp_typestack = ['RegExp'];
function match_RegExp($stack = []) {
	$matchrule = 'RegExp';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_15 = \null;
	do {
		$res_12 = $result;
		$pos_12 = $this->pos;
		$key = 'match_'.'SimpleRegExp'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_15 = \true; break;
		}
		$result = $res_12;
		$this->setPos($pos_12);
		$key = 'match_'.'StringRegExp'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_15 = \true; break;
		}
		$result = $res_12;
		$this->setPos($pos_12);
		$_15 = \false; break;
	}
	while(\false);
	if($_15 === \true) { return $this->finalise($result); }
	if($_15 === \false) { return \false; }
}

public function RegExp_SimpleRegExp (&$result, $sub) {
        $result['val'] = $sub['text'];
    }

public function RegExp_StringRegExp (&$result, $sub) {
        $result['val'] = $sub['text'];
    }

/* Name: "$"? /[A-Za-z]+/ */
protected $match_Name_typestack = ['Name'];
function match_Name($stack = []) {
	$matchrule = 'Name';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_19 = \null;
	do {
		$res_17 = $result;
		$pos_17 = $this->pos;
		if (\substr($this->string, $this->pos, 1) === '$') {
			$this->addPos(1);
			$result["text"] .= '$';
		}
		else {
			$result = $res_17;
			$this->setPos($pos_17);
			unset($res_17, $pos_17);
		}
		if (($subres = $this->rx('/[A-Za-z]+/')) !== \false) { $result["text"] .= $subres; }
		else { $_19 = \false; break; }
		$_19 = \true; break;
	}
	while(\false);
	if($_19 === \true) { return $this->finalise($result); }
	if($_19 === \false) { return \false; }
}


/* Variable: Name */
protected $match_Variable_typestack = ['Variable'];
function match_Variable($stack = []) {
	$matchrule = 'Variable';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$key = 'match_'.'Name'; $pos = $this->pos;
	$subres = $this->packhas($key, $pos)
		? $this->packread($key, $pos)
		: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
	if ($subres !== \false) {
		$this->store($result, $subres);
		return $this->finalise($result);
	}
	else { return \false; }
}

public function Variable_Name (&$result, $sub) {
       $result['val'] = $sub['text'];
   }

/* Consts: "true" | "false" | "null" */
protected $match_Consts_typestack = ['Consts'];
function match_Consts($stack = []) {
	$matchrule = 'Consts';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_29 = \null;
	do {
		$res_22 = $result;
		$pos_22 = $this->pos;
		if (($subres = $this->literal('true')) !== \false) {
			$result["text"] .= $subres;
			$_29 = \true; break;
		}
		$result = $res_22;
		$this->setPos($pos_22);
		$_27 = \null;
		do {
			$res_24 = $result;
			$pos_24 = $this->pos;
			if (($subres = $this->literal('false')) !== \false) {
				$result["text"] .= $subres;
				$_27 = \true; break;
			}
			$result = $res_24;
			$this->setPos($pos_24);
			if (($subres = $this->literal('null')) !== \false) {
				$result["text"] .= $subres;
				$_27 = \true; break;
			}
			$result = $res_24;
			$this->setPos($pos_24);
			$_27 = \false; break;
		}
		while(\false);
		if($_27 === \true) { $_29 = \true; break; }
		$result = $res_22;
		$this->setPos($pos_22);
		$_29 = \false; break;
	}
	while(\false);
	if($_29 === \true) { return $this->finalise($result); }
	if($_29 === \false) { return \false; }
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


/* Value: Consts > | RegExp > | Variable > | Number > | '(' > Expr > ')' > */
protected $match_Value_typestack = ['Value'];
function match_Value($stack = []) {
	$matchrule = 'Value';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_66 = \null;
	do {
		$res_32 = $result;
		$pos_32 = $this->pos;
		$_35 = \null;
		do {
			$key = 'match_'.'Consts'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else { $_35 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$_35 = \true; break;
		}
		while(\false);
		if($_35 === \true) { $_66 = \true; break; }
		$result = $res_32;
		$this->setPos($pos_32);
		$_64 = \null;
		do {
			$res_37 = $result;
			$pos_37 = $this->pos;
			$_40 = \null;
			do {
				$key = 'match_'.'RegExp'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_40 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_40 = \true; break;
			}
			while(\false);
			if($_40 === \true) { $_64 = \true; break; }
			$result = $res_37;
			$this->setPos($pos_37);
			$_62 = \null;
			do {
				$res_42 = $result;
				$pos_42 = $this->pos;
				$_45 = \null;
				do {
					$key = 'match_'.'Variable'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) { $this->store($result, $subres); }
					else { $_45 = \false; break; }
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					$_45 = \true; break;
				}
				while(\false);
				if($_45 === \true) { $_62 = \true; break; }
				$result = $res_42;
				$this->setPos($pos_42);
				$_60 = \null;
				do {
					$res_47 = $result;
					$pos_47 = $this->pos;
					$_50 = \null;
					do {
						$key = 'match_'.'Number'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) { $this->store($result, $subres); }
						else { $_50 = \false; break; }
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						$_50 = \true; break;
					}
					while(\false);
					if($_50 === \true) { $_60 = \true; break; }
					$result = $res_47;
					$this->setPos($pos_47);
					$_58 = \null;
					do {
						if (\substr($this->string, $this->pos, 1) === '(') {
							$this->addPos(1);
							$result["text"] .= '(';
						}
						else { $_58 = \false; break; }
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						$key = 'match_'.'Expr'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) { $this->store($result, $subres); }
						else { $_58 = \false; break; }
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						if (\substr($this->string, $this->pos, 1) === ')') {
							$this->addPos(1);
							$result["text"] .= ')';
						}
						else { $_58 = \false; break; }
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						$_58 = \true; break;
					}
					while(\false);
					if($_58 === \true) { $_60 = \true; break; }
					$result = $res_47;
					$this->setPos($pos_47);
					$_60 = \false; break;
				}
				while(\false);
				if($_60 === \true) { $_62 = \true; break; }
				$result = $res_42;
				$this->setPos($pos_42);
				$_62 = \false; break;
			}
			while(\false);
			if($_62 === \true) { $_64 = \true; break; }
			$result = $res_37;
			$this->setPos($pos_37);
			$_64 = \false; break;
		}
		while(\false);
		if($_64 === \true) { $_66 = \true; break; }
		$result = $res_32;
		$this->setPos($pos_32);
		$_66 = \false; break;
	}
	while(\false);
	if($_66 === \true) { return $this->finalise($result); }
	if($_66 === \false) { return \false; }
}

public function Value_Consts (&$result, $sub) {
        $result['val'] = $this->with_type(json_decode($sub['text']));
    }

public function Value_Variable (&$result, $sub) {
        $name = $sub['val'];
        if (array_key_exists($name, $this->constants)) {
            $result['val'] = $this->with_type($this->constants[$name]);
        } else if (array_key_exists($name, $this->variables)) {
            $result['val'] = $this->with_type($this->variables[$name]);
        } else {
            throw new Exception("Variable '$name' not found");
        }
    }

public function Value_RegExp (&$result, $sub) {
        $result['val'] = $this->with_type($sub['val'], 'regex');
    }

public function Value_Number (&$result, $sub) {
        $value = floatval($sub['text']);
        $result['val'] = $this->with_type($value);
    }

public function Value_Expr (&$result, $sub ) {
        $result['val'] = $sub['val'];
    }

/* Call: Name "(" > ( > Expr > ","? > ) * > ")" > */
protected $match_Call_typestack = ['Call'];
function match_Call($stack = []) {
	$matchrule = 'Call';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_81 = \null;
	do {
		$key = 'match_'.'Name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_81 = \false; break; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_81 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_77 = $result;
			$pos_77 = $this->pos;
			$_76 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'Expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_76 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$res_74 = $result;
				$pos_74 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else {
					$result = $res_74;
					$this->setPos($pos_74);
					unset($res_74, $pos_74);
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_76 = \true; break;
			}
			while(\false);
			if($_76 === \false) {
				$result = $res_77;
				$this->setPos($pos_77);
				unset($res_77, $pos_77);
				break;
			}
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_81 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_81 = \true; break;
	}
	while(\false);
	if($_81 === \true) { return $this->finalise($result); }
	if($_81 === \false) { return \false; }
}

public function Call_Name (&$result, $sub) {
        $name = $sub['text'];
        $result['val'] = [
            "args" => [],
            "name" => $name
        ];
    }

public function Call_Expr (&$result, $sub) {
        array_push($result['val']['args'], $sub['val']['value']);
    }

/* Negation: '-' > operand:Value > */
protected $match_Negation_typestack = ['Negation'];
function match_Negation($stack = []) {
	$matchrule = 'Negation';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_87 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_87 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_87 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_87 = \true; break;
	}
	while(\false);
	if($_87 === \true) { return $this->finalise($result); }
	if($_87 === \false) { return \false; }
}


/* ToInt: '+' > operand:Value > */
protected $match_ToInt_typestack = ['ToInt'];
function match_ToInt($stack = []) {
	$matchrule = 'ToInt';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_93 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_93 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_93 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_93 = \true; break;
	}
	while(\false);
	if($_93 === \true) { return $this->finalise($result); }
	if($_93 === \false) { return \false; }
}


/* Unnary: ( Call | Negation | ToInt | Value ) */
protected $match_Unnary_typestack = ['Unnary'];
function match_Unnary($stack = []) {
	$matchrule = 'Unnary';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_108 = \null;
	do {
		$_106 = \null;
		do {
			$res_95 = $result;
			$pos_95 = $this->pos;
			$key = 'match_'.'Call'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres);
				$_106 = \true; break;
			}
			$result = $res_95;
			$this->setPos($pos_95);
			$_104 = \null;
			do {
				$res_97 = $result;
				$pos_97 = $this->pos;
				$key = 'match_'.'Negation'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_104 = \true; break;
				}
				$result = $res_97;
				$this->setPos($pos_97);
				$_102 = \null;
				do {
					$res_99 = $result;
					$pos_99 = $this->pos;
					$key = 'match_'.'ToInt'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_102 = \true; break;
					}
					$result = $res_99;
					$this->setPos($pos_99);
					$key = 'match_'.'Value'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_102 = \true; break;
					}
					$result = $res_99;
					$this->setPos($pos_99);
					$_102 = \false; break;
				}
				while(\false);
				if($_102 === \true) { $_104 = \true; break; }
				$result = $res_97;
				$this->setPos($pos_97);
				$_104 = \false; break;
			}
			while(\false);
			if($_104 === \true) { $_106 = \true; break; }
			$result = $res_95;
			$this->setPos($pos_95);
			$_106 = \false; break;
		}
		while(\false);
		if($_106 === \false) { $_108 = \false; break; }
		$_108 = \true; break;
	}
	while(\false);
	if($_108 === \true) { return $this->finalise($result); }
	if($_108 === \false) { return \false; }
}

public function Unnary_Value (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Unnary_ToInt (&$result, $sub) {
        $val = $sub['operand']['val'];
        if ($this->is_string($val)) {
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
            throw new Exception("function '$name' doesn't exists");
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
            throw new Exception("Function '$name' expected $params_count params got $args_count");
        }
        $result['val'] = $this->with_type($function->invokeArgs($args));
    }

public function Unnary_Negation (&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->validate_number('-', $object);
        $result['val'] = $this->with_type($object['value'] * -1);
    }

/* And: "&&" > operand:Unnary > */
protected $match_And_typestack = ['And'];
function match_And($stack = []) {
	$matchrule = 'And';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_114 = \null;
	do {
		if (($subres = $this->literal('&&')) !== \false) { $result["text"] .= $subres; }
		else { $_114 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unnary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_114 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_114 = \true; break;
	}
	while(\false);
	if($_114 === \true) { return $this->finalise($result); }
	if($_114 === \false) { return \false; }
}


/* Or: "||" > operand:Unnary > */
protected $match_Or_typestack = ['Or'];
function match_Or($stack = []) {
	$matchrule = 'Or';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_120 = \null;
	do {
		if (($subres = $this->literal('||')) !== \false) { $result["text"] .= $subres; }
		else { $_120 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unnary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_120 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_120 = \true; break;
	}
	while(\false);
	if($_120 === \true) { return $this->finalise($result); }
	if($_120 === \false) { return \false; }
}


/* Boolean: Unnary > (And | Or) * */
protected $match_Boolean_typestack = ['Boolean'];
function match_Boolean($stack = []) {
	$matchrule = 'Boolean';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_131 = \null;
	do {
		$key = 'match_'.'Unnary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_131 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_130 = $result;
			$pos_130 = $this->pos;
			$_129 = \null;
			do {
				$_127 = \null;
				do {
					$res_124 = $result;
					$pos_124 = $this->pos;
					$key = 'match_'.'And'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_127 = \true; break;
					}
					$result = $res_124;
					$this->setPos($pos_124);
					$key = 'match_'.'Or'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_127 = \true; break;
					}
					$result = $res_124;
					$this->setPos($pos_124);
					$_127 = \false; break;
				}
				while(\false);
				if($_127 === \false) { $_129 = \false; break; }
				$_129 = \true; break;
			}
			while(\false);
			if($_129 === \false) {
				$result = $res_130;
				$this->setPos($pos_130);
				unset($res_130, $pos_130);
				break;
			}
		}
		$_131 = \true; break;
	}
	while(\false);
	if($_131 === \true) { return $this->finalise($result); }
	if($_131 === \false) { return \false; }
}

public function Boolean_Unnary (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Boolean_And (&$result, $sub) {
       $a = $result['val'];
       $b = $sub['operand']['val'];
       $result['val'] = $this->with_type($a['value'] ? $b['value'] : $a['value']);
    }

public function Boolean_Or (&$result, $sub) {
       $a = $result['val'];
       $b = $sub['operand']['val'];
       $result['val'] = $this->with_type($a['value'] ? $a['value'] : $b['value']);
    }

/* Times: '*' > operand:Boolean > */
protected $match_Times_typestack = ['Times'];
function match_Times($stack = []) {
	$matchrule = 'Times';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_137 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '*') {
			$this->addPos(1);
			$result["text"] .= '*';
		}
		else { $_137 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Boolean'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_137 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_137 = \true; break;
	}
	while(\false);
	if($_137 === \true) { return $this->finalise($result); }
	if($_137 === \false) { return \false; }
}


/* Div: '/' > operand:Boolean > */
protected $match_Div_typestack = ['Div'];
function match_Div($stack = []) {
	$matchrule = 'Div';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_143 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '/') {
			$this->addPos(1);
			$result["text"] .= '/';
		}
		else { $_143 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Boolean'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_143 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_143 = \true; break;
	}
	while(\false);
	if($_143 === \true) { return $this->finalise($result); }
	if($_143 === \false) { return \false; }
}


/* Mod: '%' > operand:Boolean > */
protected $match_Mod_typestack = ['Mod'];
function match_Mod($stack = []) {
	$matchrule = 'Mod';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_149 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '%') {
			$this->addPos(1);
			$result["text"] .= '%';
		}
		else { $_149 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Boolean'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_149 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_149 = \true; break;
	}
	while(\false);
	if($_149 === \true) { return $this->finalise($result); }
	if($_149 === \false) { return \false; }
}


/* Product: Boolean > ( Times | Div | Mod ) * */
protected $match_Product_typestack = ['Product'];
function match_Product($stack = []) {
	$matchrule = 'Product';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_164 = \null;
	do {
		$key = 'match_'.'Boolean'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_164 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_163 = $result;
			$pos_163 = $this->pos;
			$_162 = \null;
			do {
				$_160 = \null;
				do {
					$res_153 = $result;
					$pos_153 = $this->pos;
					$key = 'match_'.'Times'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_160 = \true; break;
					}
					$result = $res_153;
					$this->setPos($pos_153);
					$_158 = \null;
					do {
						$res_155 = $result;
						$pos_155 = $this->pos;
						$key = 'match_'.'Div'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_158 = \true; break;
						}
						$result = $res_155;
						$this->setPos($pos_155);
						$key = 'match_'.'Mod'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_158 = \true; break;
						}
						$result = $res_155;
						$this->setPos($pos_155);
						$_158 = \false; break;
					}
					while(\false);
					if($_158 === \true) { $_160 = \true; break; }
					$result = $res_153;
					$this->setPos($pos_153);
					$_160 = \false; break;
				}
				while(\false);
				if($_160 === \false) { $_162 = \false; break; }
				$_162 = \true; break;
			}
			while(\false);
			if($_162 === \false) {
				$result = $res_163;
				$this->setPos($pos_163);
				unset($res_163, $pos_163);
				break;
			}
		}
		$_164 = \true; break;
	}
	while(\false);
	if($_164 === \true) { return $this->finalise($result); }
	if($_164 === \false) { return \false; }
}

public function Product_Boolean (&$result, $sub) {
       $result['val'] = $sub['val'];
    }

public function Product_Times (&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->validate_number('*', $object);
        $result['val'] = $this->with_type($result['val']['value'] * $object['value']);
    }

public function Product_Div (&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->validate_number('*', $object);
        $result['val'] = $this->with_type($result['val']['value'] / $object['value']);
    }

public function Product_Mod (&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->validate_number('*', $object);
        $result['val'] = $this->with_type($result['val']['value'] % $object['value']);
    }

/* Plus: '+' > operand:Product > */
protected $match_Plus_typestack = ['Plus'];
function match_Plus($stack = []) {
	$matchrule = 'Plus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_170 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_170 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_170 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_170 = \true; break;
	}
	while(\false);
	if($_170 === \true) { return $this->finalise($result); }
	if($_170 === \false) { return \false; }
}


/* Minus: '-' > operand:Product > */
protected $match_Minus_typestack = ['Minus'];
function match_Minus($stack = []) {
	$matchrule = 'Minus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_176 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_176 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_176 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_176 = \true; break;
	}
	while(\false);
	if($_176 === \true) { return $this->finalise($result); }
	if($_176 === \false) { return \false; }
}


/* Sum: Product > ( Plus | Minus ) * */
protected $match_Sum_typestack = ['Sum'];
function match_Sum($stack = []) {
	$matchrule = 'Sum';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_187 = \null;
	do {
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_187 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_186 = $result;
			$pos_186 = $this->pos;
			$_185 = \null;
			do {
				$_183 = \null;
				do {
					$res_180 = $result;
					$pos_180 = $this->pos;
					$key = 'match_'.'Plus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_183 = \true; break;
					}
					$result = $res_180;
					$this->setPos($pos_180);
					$key = 'match_'.'Minus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_183 = \true; break;
					}
					$result = $res_180;
					$this->setPos($pos_180);
					$_183 = \false; break;
				}
				while(\false);
				if($_183 === \false) { $_185 = \false; break; }
				$_185 = \true; break;
			}
			while(\false);
			if($_185 === \false) {
				$result = $res_186;
				$this->setPos($pos_186);
				unset($res_186, $pos_186);
				break;
			}
		}
		$_187 = \true; break;
	}
	while(\false);
	if($_187 === \true) { return $this->finalise($result); }
	if($_187 === \false) { return \false; }
}

public function Sum_Product (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Sum_Plus (&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->validate_number('+', $object);
        if ($this->is_string($object)) {
            $result['val'] = $this->with_type($result['val']['value'] . $object['value']);
        } else {
            $result['val'] = $this->with_type($result['val']['value'] + $object['value']);
        }
    }

public function Sum_Minus (&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->validate_number('-', $object);
        $result['val'] = $this->with_type($result['val']['value'] - $object['value']);
    }

/* VariableAssignment: Variable > "=" > Expr */
protected $match_VariableAssignment_typestack = ['VariableAssignment'];
function match_VariableAssignment($stack = []) {
	$matchrule = 'VariableAssignment';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_194 = \null;
	do {
		$key = 'match_'.'Variable'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_194 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '=') {
			$this->addPos(1);
			$result["text"] .= '=';
		}
		else { $_194 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_194 = \false; break; }
		$_194 = \true; break;
	}
	while(\false);
	if($_194 === \true) { return $this->finalise($result); }
	if($_194 === \false) { return \false; }
}

public function VariableAssignment_Variable (&$result, $sub) {
        $result['val'] = ["name" => $sub['val']];
    }

public function VariableAssignment_Expr (&$result, $sub) {
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

/* Start: (VariableAssignment | Expr) ";"? */
protected $match_Start_typestack = ['Start'];
function match_Start($stack = []) {
	$matchrule = 'Start';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_205 = \null;
	do {
		$_202 = \null;
		do {
			$_200 = \null;
			do {
				$res_197 = $result;
				$pos_197 = $this->pos;
				$key = 'match_'.'VariableAssignment'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_200 = \true; break;
				}
				$result = $res_197;
				$this->setPos($pos_197);
				$key = 'match_'.'Expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_200 = \true; break;
				}
				$result = $res_197;
				$this->setPos($pos_197);
				$_200 = \false; break;
			}
			while(\false);
			if($_200 === \false) { $_202 = \false; break; }
			$_202 = \true; break;
		}
		while(\false);
		if($_202 === \false) { $_205 = \false; break; }
		$res_204 = $result;
		$pos_204 = $this->pos;
		if (\substr($this->string, $this->pos, 1) === ';') {
			$this->addPos(1);
			$result["text"] .= ';';
		}
		else {
			$result = $res_204;
			$this->setPos($pos_204);
			unset($res_204, $pos_204);
		}
		$_205 = \true; break;
	}
	while(\false);
	if($_205 === \true) { return $this->finalise($result); }
	if($_205 === \false) { return \false; }
}

public function Start_VariableAssignment (&$result, $sub) {
        $name = $sub['val']['name'];
        $value = $sub['val']['value'];
        if (array_key_exists($name, $this->constants)) {
             throw new Exception("Can't assign value to constant '$name'");
        }
        $this->variables[$name] = $value;
        $result['val'] = $value;
    }

public function Start_Expr (&$result, $sub) {
        $result['val'] = $sub['val'];
    }



}

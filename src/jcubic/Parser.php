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
      Property Access / square brackets
      boolean comparators == != < > <= >=
      bit shift (new)
      match operator =~
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
    private function maybe_regex($value) {
        $value = trim($value, '"\'');
        if (preg_match("/(\W)[^\\1]+\\1[imsxUXJ]*/", $value)) {
            return $this->with_type($value, 'regex');
        }
        return $this->with_type($value, 'string');
    }

/* Name: "$"? /[A-Za-z]+/ */
protected $match_Name_typestack = ['Name'];
function match_Name($stack = []) {
	$matchrule = 'Name';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_2 = \null;
	do {
		$res_0 = $result;
		$pos_0 = $this->pos;
		if (\substr($this->string, $this->pos, 1) === '$') {
			$this->addPos(1);
			$result["text"] .= '$';
		}
		else {
			$result = $res_0;
			$this->setPos($pos_0);
			unset($res_0, $pos_0);
		}
		if (($subres = $this->rx('/[A-Za-z]+/')) !== \false) { $result["text"] .= $subres; }
		else { $_2 = \false; break; }
		$_2 = \true; break;
	}
	while(\false);
	if($_2 === \true) { return $this->finalise($result); }
	if($_2 === \false) { return \false; }
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

/* VariableReference: Variable */
protected $match_VariableReference_typestack = ['VariableReference'];
function match_VariableReference($stack = []) {
	$matchrule = 'VariableReference';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$key = 'match_'.'Variable'; $pos = $this->pos;
	$subres = $this->packhas($key, $pos)
		? $this->packread($key, $pos)
		: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
	if ($subres !== \false) {
		$this->store($result, $subres);
		return $this->finalise($result);
	}
	else { return \false; }
}

public function VariableReference_Variable (&$result, $sub) {
        $name = $sub['val'];
        if (array_key_exists($name, $this->constants)) {
            $result['val'] = $this->with_type($this->constants[$name]);
        } else if (array_key_exists($name, $this->variables)) {
            $result['val'] = $this->with_type($this->variables[$name]);
        } else {
            throw new Exception("Variable '$name' not found");
        }
    }

/* SingleQuoted: q:/'/ ( /\\{2}/ * /\\'/ | /[^']/ ) * '$q' */
protected $match_SingleQuoted_typestack = ['SingleQuoted'];
function match_SingleQuoted($stack = []) {
	$matchrule = 'SingleQuoted';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_18 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "q");
		if (($subres = $this->rx('/\'/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'q');
		}
		else {
			$result = \array_pop($stack);
			$_18 = \false; break;
		}
		while (\true) {
			$res_16 = $result;
			$pos_16 = $this->pos;
			$_15 = \null;
			do {
				$_13 = \null;
				do {
					$res_7 = $result;
					$pos_7 = $this->pos;
					$_10 = \null;
					do {
						while (\true) {
							$res_8 = $result;
							$pos_8 = $this->pos;
							if (($subres = $this->rx('/\\\\{2}/')) !== \false) { $result["text"] .= $subres; }
							else {
								$result = $res_8;
								$this->setPos($pos_8);
								unset($res_8, $pos_8);
								break;
							}
						}
						if (($subres = $this->rx('/\\\\\'/')) !== \false) { $result["text"] .= $subres; }
						else { $_10 = \false; break; }
						$_10 = \true; break;
					}
					while(\false);
					if($_10 === \true) { $_13 = \true; break; }
					$result = $res_7;
					$this->setPos($pos_7);
					if (($subres = $this->rx('/[^\']/')) !== \false) {
						$result["text"] .= $subres;
						$_13 = \true; break;
					}
					$result = $res_7;
					$this->setPos($pos_7);
					$_13 = \false; break;
				}
				while(\false);
				if($_13 === \false) { $_15 = \false; break; }
				$_15 = \true; break;
			}
			while(\false);
			if($_15 === \false) {
				$result = $res_16;
				$this->setPos($pos_16);
				unset($res_16, $pos_16);
				break;
			}
		}
		if (($subres = $this->literal(''.$this->expression($result, $stack, 'q').'')) !== \false) { $result["text"] .= $subres; }
		else { $_18 = \false; break; }
		$_18 = \true; break;
	}
	while(\false);
	if($_18 === \true) { return $this->finalise($result); }
	if($_18 === \false) { return \false; }
}


/* DoubleQuoted: q:/"/ ( /\\{2}/ * /\\"/ | /[^"]/ ) * '$q' */
protected $match_DoubleQuoted_typestack = ['DoubleQuoted'];
function match_DoubleQuoted($stack = []) {
	$matchrule = 'DoubleQuoted';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_32 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "q");
		if (($subres = $this->rx('/"/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'q');
		}
		else {
			$result = \array_pop($stack);
			$_32 = \false; break;
		}
		while (\true) {
			$res_30 = $result;
			$pos_30 = $this->pos;
			$_29 = \null;
			do {
				$_27 = \null;
				do {
					$res_21 = $result;
					$pos_21 = $this->pos;
					$_24 = \null;
					do {
						while (\true) {
							$res_22 = $result;
							$pos_22 = $this->pos;
							if (($subres = $this->rx('/\\\\{2}/')) !== \false) { $result["text"] .= $subres; }
							else {
								$result = $res_22;
								$this->setPos($pos_22);
								unset($res_22, $pos_22);
								break;
							}
						}
						if (($subres = $this->rx('/\\\\"/')) !== \false) { $result["text"] .= $subres; }
						else { $_24 = \false; break; }
						$_24 = \true; break;
					}
					while(\false);
					if($_24 === \true) { $_27 = \true; break; }
					$result = $res_21;
					$this->setPos($pos_21);
					if (($subres = $this->rx('/[^"]/')) !== \false) {
						$result["text"] .= $subres;
						$_27 = \true; break;
					}
					$result = $res_21;
					$this->setPos($pos_21);
					$_27 = \false; break;
				}
				while(\false);
				if($_27 === \false) { $_29 = \false; break; }
				$_29 = \true; break;
			}
			while(\false);
			if($_29 === \false) {
				$result = $res_30;
				$this->setPos($pos_30);
				unset($res_30, $pos_30);
				break;
			}
		}
		if (($subres = $this->literal(''.$this->expression($result, $stack, 'q').'')) !== \false) { $result["text"] .= $subres; }
		else { $_32 = \false; break; }
		$_32 = \true; break;
	}
	while(\false);
	if($_32 === \true) { return $this->finalise($result); }
	if($_32 === \false) { return \false; }
}


/* String: SingleQuoted | DoubleQuoted */
protected $match_String_typestack = ['String'];
function match_String($stack = []) {
	$matchrule = 'String';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_37 = \null;
	do {
		$res_34 = $result;
		$pos_34 = $this->pos;
		$key = 'match_'.'SingleQuoted'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_37 = \true; break;
		}
		$result = $res_34;
		$this->setPos($pos_34);
		$key = 'match_'.'DoubleQuoted'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_37 = \true; break;
		}
		$result = $res_34;
		$this->setPos($pos_34);
		$_37 = \false; break;
	}
	while(\false);
	if($_37 === \true) { return $this->finalise($result); }
	if($_37 === \false) { return \false; }
}

public function String_SingleQuoted (&$result, $sub) {
         $result['val'] = trim($sub['text'], "'");
    }

public function String_DoubleQuoted (&$result, $sub) {
         $result['val'] = trim($sub['text'], '"');
    }

/* Consts: "true" | "false" | "null" */
protected $match_Consts_typestack = ['Consts'];
function match_Consts($stack = []) {
	$matchrule = 'Consts';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_46 = \null;
	do {
		$res_39 = $result;
		$pos_39 = $this->pos;
		if (($subres = $this->literal('true')) !== \false) {
			$result["text"] .= $subres;
			$_46 = \true; break;
		}
		$result = $res_39;
		$this->setPos($pos_39);
		$_44 = \null;
		do {
			$res_41 = $result;
			$pos_41 = $this->pos;
			if (($subres = $this->literal('false')) !== \false) {
				$result["text"] .= $subres;
				$_44 = \true; break;
			}
			$result = $res_41;
			$this->setPos($pos_41);
			if (($subres = $this->literal('null')) !== \false) {
				$result["text"] .= $subres;
				$_44 = \true; break;
			}
			$result = $res_41;
			$this->setPos($pos_41);
			$_44 = \false; break;
		}
		while(\false);
		if($_44 === \true) { $_46 = \true; break; }
		$result = $res_39;
		$this->setPos($pos_39);
		$_46 = \false; break;
	}
	while(\false);
	if($_46 === \true) { return $this->finalise($result); }
	if($_46 === \false) { return \false; }
}


/* RegExp: /(?<!\\\\)\/(?:[^\/]|\\\\\/)+\// /[imsxUXJ]/* */
protected $match_RegExp_typestack = ['RegExp'];
function match_RegExp($stack = []) {
	$matchrule = 'RegExp';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_50 = \null;
	do {
		if (($subres = $this->rx('/(?<!\\\\\\\\)\/(?:[^\/]|\\\\\\\\\/)+\//')) !== \false) { $result["text"] .= $subres; }
		else { $_50 = \false; break; }
		while (\true) {
			$res_49 = $result;
			$pos_49 = $this->pos;
			if (($subres = $this->rx('/[imsxUXJ]/')) !== \false) { $result["text"] .= $subres; }
			else {
				$result = $res_49;
				$this->setPos($pos_49);
				unset($res_49, $pos_49);
				break;
			}
		}
		$_50 = \true; break;
	}
	while(\false);
	if($_50 === \true) { return $this->finalise($result); }
	if($_50 === \false) { return \false; }
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


/* Value: Consts > | RegExp > | String > | VariableReference > | Number > | '(' > Expr > ')' > */
protected $match_Value_typestack = ['Value'];
function match_Value($stack = []) {
	$matchrule = 'Value';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_94 = \null;
	do {
		$res_53 = $result;
		$pos_53 = $this->pos;
		$_56 = \null;
		do {
			$key = 'match_'.'Consts'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else { $_56 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$_56 = \true; break;
		}
		while(\false);
		if($_56 === \true) { $_94 = \true; break; }
		$result = $res_53;
		$this->setPos($pos_53);
		$_92 = \null;
		do {
			$res_58 = $result;
			$pos_58 = $this->pos;
			$_61 = \null;
			do {
				$key = 'match_'.'RegExp'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_61 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_61 = \true; break;
			}
			while(\false);
			if($_61 === \true) { $_92 = \true; break; }
			$result = $res_58;
			$this->setPos($pos_58);
			$_90 = \null;
			do {
				$res_63 = $result;
				$pos_63 = $this->pos;
				$_66 = \null;
				do {
					$key = 'match_'.'String'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) { $this->store($result, $subres); }
					else { $_66 = \false; break; }
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					$_66 = \true; break;
				}
				while(\false);
				if($_66 === \true) { $_90 = \true; break; }
				$result = $res_63;
				$this->setPos($pos_63);
				$_88 = \null;
				do {
					$res_68 = $result;
					$pos_68 = $this->pos;
					$_71 = \null;
					do {
						$key = 'match_'.'VariableReference'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) { $this->store($result, $subres); }
						else { $_71 = \false; break; }
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						$_71 = \true; break;
					}
					while(\false);
					if($_71 === \true) { $_88 = \true; break; }
					$result = $res_68;
					$this->setPos($pos_68);
					$_86 = \null;
					do {
						$res_73 = $result;
						$pos_73 = $this->pos;
						$_76 = \null;
						do {
							$key = 'match_'.'Number'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) { $this->store($result, $subres); }
							else { $_76 = \false; break; }
							if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
							$_76 = \true; break;
						}
						while(\false);
						if($_76 === \true) { $_86 = \true; break; }
						$result = $res_73;
						$this->setPos($pos_73);
						$_84 = \null;
						do {
							if (\substr($this->string, $this->pos, 1) === '(') {
								$this->addPos(1);
								$result["text"] .= '(';
							}
							else { $_84 = \false; break; }
							if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
							$key = 'match_'.'Expr'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) { $this->store($result, $subres); }
							else { $_84 = \false; break; }
							if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
							if (\substr($this->string, $this->pos, 1) === ')') {
								$this->addPos(1);
								$result["text"] .= ')';
							}
							else { $_84 = \false; break; }
							if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
							$_84 = \true; break;
						}
						while(\false);
						if($_84 === \true) { $_86 = \true; break; }
						$result = $res_73;
						$this->setPos($pos_73);
						$_86 = \false; break;
					}
					while(\false);
					if($_86 === \true) { $_88 = \true; break; }
					$result = $res_68;
					$this->setPos($pos_68);
					$_88 = \false; break;
				}
				while(\false);
				if($_88 === \true) { $_90 = \true; break; }
				$result = $res_63;
				$this->setPos($pos_63);
				$_90 = \false; break;
			}
			while(\false);
			if($_90 === \true) { $_92 = \true; break; }
			$result = $res_58;
			$this->setPos($pos_58);
			$_92 = \false; break;
		}
		while(\false);
		if($_92 === \true) { $_94 = \true; break; }
		$result = $res_53;
		$this->setPos($pos_53);
		$_94 = \false; break;
	}
	while(\false);
	if($_94 === \true) { return $this->finalise($result); }
	if($_94 === \false) { return \false; }
}

public function Value_Consts (&$result, $sub) {
        $result['val'] = $this->with_type(json_decode($sub['text']));
    }

public function Value_RegExp (&$result, $sub) {
        $result['val'] = $this->with_type($sub['text'], 'regex');
    }

public function Value_String (&$result, $sub) {
        $result['val'] = $this->maybe_regex($sub['val']);
    }

public function Value_VariableReference (&$result, $sub) {
        $result['val'] = $sub['val'];
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
	$_109 = \null;
	do {
		$key = 'match_'.'Name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_109 = \false; break; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_109 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_105 = $result;
			$pos_105 = $this->pos;
			$_104 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'Expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_104 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$res_102 = $result;
				$pos_102 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else {
					$result = $res_102;
					$this->setPos($pos_102);
					unset($res_102, $pos_102);
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_104 = \true; break;
			}
			while(\false);
			if($_104 === \false) {
				$result = $res_105;
				$this->setPos($pos_105);
				unset($res_105, $pos_105);
				break;
			}
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_109 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_109 = \true; break;
	}
	while(\false);
	if($_109 === \true) { return $this->finalise($result); }
	if($_109 === \false) { return \false; }
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

/* FunctionCall: Call */
protected $match_FunctionCall_typestack = ['FunctionCall'];
function match_FunctionCall($stack = []) {
	$matchrule = 'FunctionCall';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$key = 'match_'.'Call'; $pos = $this->pos;
	$subres = $this->packhas($key, $pos)
		? $this->packread($key, $pos)
		: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
	if ($subres !== \false) {
		$this->store($result, $subres);
		return $this->finalise($result);
	}
	else { return \false; }
}

public function FunctionCall_Call (&$result, $sub) {
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

/* Negation: '-' > operand:Value > */
protected $match_Negation_typestack = ['Negation'];
function match_Negation($stack = []) {
	$matchrule = 'Negation';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_116 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_116 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_116 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_116 = \true; break;
	}
	while(\false);
	if($_116 === \true) { return $this->finalise($result); }
	if($_116 === \false) { return \false; }
}


/* ToInt: '+' > operand:Value > */
protected $match_ToInt_typestack = ['ToInt'];
function match_ToInt($stack = []) {
	$matchrule = 'ToInt';
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
		$key = 'match_'.'Value'; $pos = $this->pos;
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


/* Unnary: ( FunctionCall | Negation | ToInt | Value ) */
protected $match_Unnary_typestack = ['Unnary'];
function match_Unnary($stack = []) {
	$matchrule = 'Unnary';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_137 = \null;
	do {
		$_135 = \null;
		do {
			$res_124 = $result;
			$pos_124 = $this->pos;
			$key = 'match_'.'FunctionCall'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres);
				$_135 = \true; break;
			}
			$result = $res_124;
			$this->setPos($pos_124);
			$_133 = \null;
			do {
				$res_126 = $result;
				$pos_126 = $this->pos;
				$key = 'match_'.'Negation'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_133 = \true; break;
				}
				$result = $res_126;
				$this->setPos($pos_126);
				$_131 = \null;
				do {
					$res_128 = $result;
					$pos_128 = $this->pos;
					$key = 'match_'.'ToInt'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_131 = \true; break;
					}
					$result = $res_128;
					$this->setPos($pos_128);
					$key = 'match_'.'Value'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_131 = \true; break;
					}
					$result = $res_128;
					$this->setPos($pos_128);
					$_131 = \false; break;
				}
				while(\false);
				if($_131 === \true) { $_133 = \true; break; }
				$result = $res_126;
				$this->setPos($pos_126);
				$_133 = \false; break;
			}
			while(\false);
			if($_133 === \true) { $_135 = \true; break; }
			$result = $res_124;
			$this->setPos($pos_124);
			$_135 = \false; break;
		}
		while(\false);
		if($_135 === \false) { $_137 = \false; break; }
		$_137 = \true; break;
	}
	while(\false);
	if($_137 === \true) { return $this->finalise($result); }
	if($_137 === \false) { return \false; }
}

public function Unnary_Value (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Unnary_FunctionCall (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Unnary_ToInt (&$result, $sub) {
        $val = $sub['operand']['val'];
        if ($this->is_string($val)) {
            $val = floatval($val);
        }
        $result['val'] = $val;
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
	$_143 = \null;
	do {
		if (($subres = $this->literal('&&')) !== \false) { $result["text"] .= $subres; }
		else { $_143 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unnary'; $pos = $this->pos;
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


/* Or: "||" > operand:Unnary > */
protected $match_Or_typestack = ['Or'];
function match_Or($stack = []) {
	$matchrule = 'Or';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_149 = \null;
	do {
		if (($subres = $this->literal('||')) !== \false) { $result["text"] .= $subres; }
		else { $_149 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unnary'; $pos = $this->pos;
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


/* Boolean: Unnary > (And | Or ) * */
protected $match_Boolean_typestack = ['Boolean'];
function match_Boolean($stack = []) {
	$matchrule = 'Boolean';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_160 = \null;
	do {
		$key = 'match_'.'Unnary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_160 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_159 = $result;
			$pos_159 = $this->pos;
			$_158 = \null;
			do {
				$_156 = \null;
				do {
					$res_153 = $result;
					$pos_153 = $this->pos;
					$key = 'match_'.'And'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_156 = \true; break;
					}
					$result = $res_153;
					$this->setPos($pos_153);
					$key = 'match_'.'Or'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_156 = \true; break;
					}
					$result = $res_153;
					$this->setPos($pos_153);
					$_156 = \false; break;
				}
				while(\false);
				if($_156 === \false) { $_158 = \false; break; }
				$_158 = \true; break;
			}
			while(\false);
			if($_158 === \false) {
				$result = $res_159;
				$this->setPos($pos_159);
				unset($res_159, $pos_159);
				break;
			}
		}
		$_160 = \true; break;
	}
	while(\false);
	if($_160 === \true) { return $this->finalise($result); }
	if($_160 === \false) { return \false; }
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

/* ImplicitTimes: FunctionCall > | VariableReference > | '(' > Expr > ')' > */
protected $match_ImplicitTimes_typestack = ['ImplicitTimes'];
function match_ImplicitTimes($stack = []) {
	$matchrule = 'ImplicitTimes';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_182 = \null;
	do {
		$res_162 = $result;
		$pos_162 = $this->pos;
		$_165 = \null;
		do {
			$key = 'match_'.'FunctionCall'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else { $_165 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$_165 = \true; break;
		}
		while(\false);
		if($_165 === \true) { $_182 = \true; break; }
		$result = $res_162;
		$this->setPos($pos_162);
		$_180 = \null;
		do {
			$res_167 = $result;
			$pos_167 = $this->pos;
			$_170 = \null;
			do {
				$key = 'match_'.'VariableReference'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_170 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_170 = \true; break;
			}
			while(\false);
			if($_170 === \true) { $_180 = \true; break; }
			$result = $res_167;
			$this->setPos($pos_167);
			$_178 = \null;
			do {
				if (\substr($this->string, $this->pos, 1) === '(') {
					$this->addPos(1);
					$result["text"] .= '(';
				}
				else { $_178 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'Expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_178 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				if (\substr($this->string, $this->pos, 1) === ')') {
					$this->addPos(1);
					$result["text"] .= ')';
				}
				else { $_178 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_178 = \true; break;
			}
			while(\false);
			if($_178 === \true) { $_180 = \true; break; }
			$result = $res_167;
			$this->setPos($pos_167);
			$_180 = \false; break;
		}
		while(\false);
		if($_180 === \true) { $_182 = \true; break; }
		$result = $res_162;
		$this->setPos($pos_162);
		$_182 = \false; break;
	}
	while(\false);
	if($_182 === \true) { return $this->finalise($result); }
	if($_182 === \false) { return \false; }
}

public function ImplicitTimes_FunctionCall (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function ImplicitTimes_Expr (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function ImplicitTimes_VariableReference (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

/* Times: '*' > operand:Boolean > */
protected $match_Times_typestack = ['Times'];
function match_Times($stack = []) {
	$matchrule = 'Times';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_188 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '*') {
			$this->addPos(1);
			$result["text"] .= '*';
		}
		else { $_188 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Boolean'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_188 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_188 = \true; break;
	}
	while(\false);
	if($_188 === \true) { return $this->finalise($result); }
	if($_188 === \false) { return \false; }
}


/* Div: '/' > operand:Boolean > */
protected $match_Div_typestack = ['Div'];
function match_Div($stack = []) {
	$matchrule = 'Div';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_194 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '/') {
			$this->addPos(1);
			$result["text"] .= '/';
		}
		else { $_194 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Boolean'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_194 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_194 = \true; break;
	}
	while(\false);
	if($_194 === \true) { return $this->finalise($result); }
	if($_194 === \false) { return \false; }
}


/* Mod: '%' > operand:Boolean > */
protected $match_Mod_typestack = ['Mod'];
function match_Mod($stack = []) {
	$matchrule = 'Mod';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_200 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '%') {
			$this->addPos(1);
			$result["text"] .= '%';
		}
		else { $_200 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Boolean'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_200 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_200 = \true; break;
	}
	while(\false);
	if($_200 === \true) { return $this->finalise($result); }
	if($_200 === \false) { return \false; }
}


/* Product: Boolean > ( Times | ImplicitTimes | Div | Mod ) * */
protected $match_Product_typestack = ['Product'];
function match_Product($stack = []) {
	$matchrule = 'Product';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_219 = \null;
	do {
		$key = 'match_'.'Boolean'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_219 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_218 = $result;
			$pos_218 = $this->pos;
			$_217 = \null;
			do {
				$_215 = \null;
				do {
					$res_204 = $result;
					$pos_204 = $this->pos;
					$key = 'match_'.'Times'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_215 = \true; break;
					}
					$result = $res_204;
					$this->setPos($pos_204);
					$_213 = \null;
					do {
						$res_206 = $result;
						$pos_206 = $this->pos;
						$key = 'match_'.'ImplicitTimes'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_213 = \true; break;
						}
						$result = $res_206;
						$this->setPos($pos_206);
						$_211 = \null;
						do {
							$res_208 = $result;
							$pos_208 = $this->pos;
							$key = 'match_'.'Div'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres);
								$_211 = \true; break;
							}
							$result = $res_208;
							$this->setPos($pos_208);
							$key = 'match_'.'Mod'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres);
								$_211 = \true; break;
							}
							$result = $res_208;
							$this->setPos($pos_208);
							$_211 = \false; break;
						}
						while(\false);
						if($_211 === \true) { $_213 = \true; break; }
						$result = $res_206;
						$this->setPos($pos_206);
						$_213 = \false; break;
					}
					while(\false);
					if($_213 === \true) { $_215 = \true; break; }
					$result = $res_204;
					$this->setPos($pos_204);
					$_215 = \false; break;
				}
				while(\false);
				if($_215 === \false) { $_217 = \false; break; }
				$_217 = \true; break;
			}
			while(\false);
			if($_217 === \false) {
				$result = $res_218;
				$this->setPos($pos_218);
				unset($res_218, $pos_218);
				break;
			}
		}
		$_219 = \true; break;
	}
	while(\false);
	if($_219 === \true) { return $this->finalise($result); }
	if($_219 === \false) { return \false; }
}

public function Product_Boolean (&$result, $sub) {
       $result['val'] = $sub['val'];
    }

public function Product_ImplicitTimes (&$result, $sub) {
        $object = $sub['val'];
        $this->validate_number('*', $object);
        $result['val'] = $this->with_type($result['val']['value'] * $object['value']);
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
	$_225 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_225 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_225 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_225 = \true; break;
	}
	while(\false);
	if($_225 === \true) { return $this->finalise($result); }
	if($_225 === \false) { return \false; }
}


/* Minus: '-' > operand:Product > */
protected $match_Minus_typestack = ['Minus'];
function match_Minus($stack = []) {
	$matchrule = 'Minus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_231 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_231 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_231 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_231 = \true; break;
	}
	while(\false);
	if($_231 === \true) { return $this->finalise($result); }
	if($_231 === \false) { return \false; }
}


/* Sum: Product > ( Plus | Minus ) * */
protected $match_Sum_typestack = ['Sum'];
function match_Sum($stack = []) {
	$matchrule = 'Sum';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_242 = \null;
	do {
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_242 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_241 = $result;
			$pos_241 = $this->pos;
			$_240 = \null;
			do {
				$_238 = \null;
				do {
					$res_235 = $result;
					$pos_235 = $this->pos;
					$key = 'match_'.'Plus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_238 = \true; break;
					}
					$result = $res_235;
					$this->setPos($pos_235);
					$key = 'match_'.'Minus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_238 = \true; break;
					}
					$result = $res_235;
					$this->setPos($pos_235);
					$_238 = \false; break;
				}
				while(\false);
				if($_238 === \false) { $_240 = \false; break; }
				$_240 = \true; break;
			}
			while(\false);
			if($_240 === \false) {
				$result = $res_241;
				$this->setPos($pos_241);
				unset($res_241, $pos_241);
				break;
			}
		}
		$_242 = \true; break;
	}
	while(\false);
	if($_242 === \true) { return $this->finalise($result); }
	if($_242 === \false) { return \false; }
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
	$_249 = \null;
	do {
		$key = 'match_'.'Variable'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_249 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '=') {
			$this->addPos(1);
			$result["text"] .= '=';
		}
		else { $_249 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_249 = \false; break; }
		$_249 = \true; break;
	}
	while(\false);
	if($_249 === \true) { return $this->finalise($result); }
	if($_249 === \false) { return \false; }
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
	$_260 = \null;
	do {
		$_257 = \null;
		do {
			$_255 = \null;
			do {
				$res_252 = $result;
				$pos_252 = $this->pos;
				$key = 'match_'.'VariableAssignment'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_255 = \true; break;
				}
				$result = $res_252;
				$this->setPos($pos_252);
				$key = 'match_'.'Expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_255 = \true; break;
				}
				$result = $res_252;
				$this->setPos($pos_252);
				$_255 = \false; break;
			}
			while(\false);
			if($_255 === \false) { $_257 = \false; break; }
			$_257 = \true; break;
		}
		while(\false);
		if($_257 === \false) { $_260 = \false; break; }
		$res_259 = $result;
		$pos_259 = $this->pos;
		if (\substr($this->string, $this->pos, 1) === ';') {
			$this->addPos(1);
			$result["text"] .= ';';
		}
		else {
			$result = $res_259;
			$this->setPos($pos_259);
			unset($res_259, $pos_259);
		}
		$_260 = \true; break;
	}
	while(\false);
	if($_260 === \true) { return $this->finalise($result); }
	if($_260 === \false) { return \false; }
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

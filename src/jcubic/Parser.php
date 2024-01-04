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

TODO: JSON objects
      Property Access / square brackets
      bit shift (new)
      match operator =~
      === !==
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
    private function is_array($value) {
        return $this->is_type('array', $value);
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
    private function check_equal(&$result, $object, $fn) {
       $a = $object['value'];
       $b = $result['val']['value'];
       if ($this->is_array($object)) {
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

/* Equal: '==' > operand:Boolean > */
protected $match_Equal_typestack = ['Equal'];
function match_Equal($stack = []) {
	$matchrule = 'Equal';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_188 = \null;
	do {
		if (($subres = $this->literal('==')) !== \false) { $result["text"] .= $subres; }
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


/* NotEqual: '!=' > operand:Boolean > */
protected $match_NotEqual_typestack = ['NotEqual'];
function match_NotEqual($stack = []) {
	$matchrule = 'NotEqual';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_194 = \null;
	do {
		if (($subres = $this->literal('!=')) !== \false) { $result["text"] .= $subres; }
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


/* GreaterEqualThan: '>=' > operand:Boolean > */
protected $match_GreaterEqualThan_typestack = ['GreaterEqualThan'];
function match_GreaterEqualThan($stack = []) {
	$matchrule = 'GreaterEqualThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_200 = \null;
	do {
		if (($subres = $this->literal('>=')) !== \false) { $result["text"] .= $subres; }
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


/* LessEqualThan: '<=' > operand:Boolean > */
protected $match_LessEqualThan_typestack = ['LessEqualThan'];
function match_LessEqualThan($stack = []) {
	$matchrule = 'LessEqualThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_206 = \null;
	do {
		if (($subres = $this->literal('<=')) !== \false) { $result["text"] .= $subres; }
		else { $_206 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Boolean'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_206 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_206 = \true; break;
	}
	while(\false);
	if($_206 === \true) { return $this->finalise($result); }
	if($_206 === \false) { return \false; }
}


/* GreaterThan: '>' > operand:Boolean > */
protected $match_GreaterThan_typestack = ['GreaterThan'];
function match_GreaterThan($stack = []) {
	$matchrule = 'GreaterThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_212 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '>') {
			$this->addPos(1);
			$result["text"] .= '>';
		}
		else { $_212 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Boolean'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_212 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_212 = \true; break;
	}
	while(\false);
	if($_212 === \true) { return $this->finalise($result); }
	if($_212 === \false) { return \false; }
}


/* LessThan: '<' > operand:Boolean > */
protected $match_LessThan_typestack = ['LessThan'];
function match_LessThan($stack = []) {
	$matchrule = 'LessThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_218 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '<') {
			$this->addPos(1);
			$result["text"] .= '<';
		}
		else { $_218 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Boolean'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_218 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_218 = \true; break;
	}
	while(\false);
	if($_218 === \true) { return $this->finalise($result); }
	if($_218 === \false) { return \false; }
}


/* Compare: Boolean > (Equal | NotEqual | GreaterEqualThan | GreaterThan | LessEqualThan | LessThan ) * */
protected $match_Compare_typestack = ['Compare'];
function match_Compare($stack = []) {
	$matchrule = 'Compare';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_245 = \null;
	do {
		$key = 'match_'.'Boolean'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_245 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_244 = $result;
			$pos_244 = $this->pos;
			$_243 = \null;
			do {
				$_241 = \null;
				do {
					$res_222 = $result;
					$pos_222 = $this->pos;
					$key = 'match_'.'Equal'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_241 = \true; break;
					}
					$result = $res_222;
					$this->setPos($pos_222);
					$_239 = \null;
					do {
						$res_224 = $result;
						$pos_224 = $this->pos;
						$key = 'match_'.'NotEqual'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_239 = \true; break;
						}
						$result = $res_224;
						$this->setPos($pos_224);
						$_237 = \null;
						do {
							$res_226 = $result;
							$pos_226 = $this->pos;
							$key = 'match_'.'GreaterEqualThan'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres);
								$_237 = \true; break;
							}
							$result = $res_226;
							$this->setPos($pos_226);
							$_235 = \null;
							do {
								$res_228 = $result;
								$pos_228 = $this->pos;
								$key = 'match_'.'GreaterThan'; $pos = $this->pos;
								$subres = $this->packhas($key, $pos)
									? $this->packread($key, $pos)
									: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
								if ($subres !== \false) {
									$this->store($result, $subres);
									$_235 = \true; break;
								}
								$result = $res_228;
								$this->setPos($pos_228);
								$_233 = \null;
								do {
									$res_230 = $result;
									$pos_230 = $this->pos;
									$key = 'match_'.'LessEqualThan'; $pos = $this->pos;
									$subres = $this->packhas($key, $pos)
										? $this->packread($key, $pos)
										: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
									if ($subres !== \false) {
										$this->store($result, $subres);
										$_233 = \true; break;
									}
									$result = $res_230;
									$this->setPos($pos_230);
									$key = 'match_'.'LessThan'; $pos = $this->pos;
									$subres = $this->packhas($key, $pos)
										? $this->packread($key, $pos)
										: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
									if ($subres !== \false) {
										$this->store($result, $subres);
										$_233 = \true; break;
									}
									$result = $res_230;
									$this->setPos($pos_230);
									$_233 = \false; break;
								}
								while(\false);
								if($_233 === \true) { $_235 = \true; break; }
								$result = $res_228;
								$this->setPos($pos_228);
								$_235 = \false; break;
							}
							while(\false);
							if($_235 === \true) { $_237 = \true; break; }
							$result = $res_226;
							$this->setPos($pos_226);
							$_237 = \false; break;
						}
						while(\false);
						if($_237 === \true) { $_239 = \true; break; }
						$result = $res_224;
						$this->setPos($pos_224);
						$_239 = \false; break;
					}
					while(\false);
					if($_239 === \true) { $_241 = \true; break; }
					$result = $res_222;
					$this->setPos($pos_222);
					$_241 = \false; break;
				}
				while(\false);
				if($_241 === \false) { $_243 = \false; break; }
				$_243 = \true; break;
			}
			while(\false);
			if($_243 === \false) {
				$result = $res_244;
				$this->setPos($pos_244);
				unset($res_244, $pos_244);
				break;
			}
		}
		$_245 = \true; break;
	}
	while(\false);
	if($_245 === \true) { return $this->finalise($result); }
	if($_245 === \false) { return \false; }
}

public function Compare_Boolean (&$result, $sub) {
       $result['val'] = $sub['val'];
    }

public function Compare_Equal (&$result, $sub) {
       $this->check_equal($result, $sub['operand']['val'], function($a, $b) {
          return $a == $b;
       });
    }

public function Compare_NotEqual (&$result, $sub) {
       $this->check_equal($result, $sub['operand']['val'], function($a, $b) {
          return $a != $b;
       });
    }

public function Compare_GreaterEqualThan (&$result, $sub) {
       $object = $sub['operand']['val'];
       $this->compare($result, $object, '>=', function($a, $b) {
           return $a >= $b;
       });
    }

public function Compare_LessEqualThan (&$result, $sub) {
       $object = $sub['operand']['val'];
       $this->compare($result, $object, '>=', function($a, $b) {
           return $a <= $b;
       });
    }

public function Compare_GreaterThan (&$result, $sub) {
       $object = $sub['operand']['val'];
       $this->compare($result, $object, '>=', function($a, $b) {
           return $a > $b;
       });
    }

public function Compare_LessThan (&$result, $sub) {
       $object = $sub['operand']['val'];
       $this->compare($result, $object, '>=', function($a, $b) {
           return $a < $b;
       });
    }

/* Times: '*' > operand:Compare > */
protected $match_Times_typestack = ['Times'];
function match_Times($stack = []) {
	$matchrule = 'Times';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_251 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '*') {
			$this->addPos(1);
			$result["text"] .= '*';
		}
		else { $_251 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_251 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_251 = \true; break;
	}
	while(\false);
	if($_251 === \true) { return $this->finalise($result); }
	if($_251 === \false) { return \false; }
}


/* Div: '/' > operand:Compare > */
protected $match_Div_typestack = ['Div'];
function match_Div($stack = []) {
	$matchrule = 'Div';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_257 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '/') {
			$this->addPos(1);
			$result["text"] .= '/';
		}
		else { $_257 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_257 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_257 = \true; break;
	}
	while(\false);
	if($_257 === \true) { return $this->finalise($result); }
	if($_257 === \false) { return \false; }
}


/* Mod: '%' > operand:Compare > */
protected $match_Mod_typestack = ['Mod'];
function match_Mod($stack = []) {
	$matchrule = 'Mod';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_263 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '%') {
			$this->addPos(1);
			$result["text"] .= '%';
		}
		else { $_263 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_263 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_263 = \true; break;
	}
	while(\false);
	if($_263 === \true) { return $this->finalise($result); }
	if($_263 === \false) { return \false; }
}


/* Product: Compare > ( Times | ImplicitTimes | Div | Mod ) * */
protected $match_Product_typestack = ['Product'];
function match_Product($stack = []) {
	$matchrule = 'Product';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_282 = \null;
	do {
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_282 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_281 = $result;
			$pos_281 = $this->pos;
			$_280 = \null;
			do {
				$_278 = \null;
				do {
					$res_267 = $result;
					$pos_267 = $this->pos;
					$key = 'match_'.'Times'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_278 = \true; break;
					}
					$result = $res_267;
					$this->setPos($pos_267);
					$_276 = \null;
					do {
						$res_269 = $result;
						$pos_269 = $this->pos;
						$key = 'match_'.'ImplicitTimes'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_276 = \true; break;
						}
						$result = $res_269;
						$this->setPos($pos_269);
						$_274 = \null;
						do {
							$res_271 = $result;
							$pos_271 = $this->pos;
							$key = 'match_'.'Div'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres);
								$_274 = \true; break;
							}
							$result = $res_271;
							$this->setPos($pos_271);
							$key = 'match_'.'Mod'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres);
								$_274 = \true; break;
							}
							$result = $res_271;
							$this->setPos($pos_271);
							$_274 = \false; break;
						}
						while(\false);
						if($_274 === \true) { $_276 = \true; break; }
						$result = $res_269;
						$this->setPos($pos_269);
						$_276 = \false; break;
					}
					while(\false);
					if($_276 === \true) { $_278 = \true; break; }
					$result = $res_267;
					$this->setPos($pos_267);
					$_278 = \false; break;
				}
				while(\false);
				if($_278 === \false) { $_280 = \false; break; }
				$_280 = \true; break;
			}
			while(\false);
			if($_280 === \false) {
				$result = $res_281;
				$this->setPos($pos_281);
				unset($res_281, $pos_281);
				break;
			}
		}
		$_282 = \true; break;
	}
	while(\false);
	if($_282 === \true) { return $this->finalise($result); }
	if($_282 === \false) { return \false; }
}

public function Product_Compare (&$result, $sub) {
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
	$_288 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_288 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_288 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_288 = \true; break;
	}
	while(\false);
	if($_288 === \true) { return $this->finalise($result); }
	if($_288 === \false) { return \false; }
}


/* Minus: '-' > operand:Product > */
protected $match_Minus_typestack = ['Minus'];
function match_Minus($stack = []) {
	$matchrule = 'Minus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_294 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_294 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_294 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_294 = \true; break;
	}
	while(\false);
	if($_294 === \true) { return $this->finalise($result); }
	if($_294 === \false) { return \false; }
}


/* Sum: Product > ( Plus | Minus ) * */
protected $match_Sum_typestack = ['Sum'];
function match_Sum($stack = []) {
	$matchrule = 'Sum';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_305 = \null;
	do {
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_305 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_304 = $result;
			$pos_304 = $this->pos;
			$_303 = \null;
			do {
				$_301 = \null;
				do {
					$res_298 = $result;
					$pos_298 = $this->pos;
					$key = 'match_'.'Plus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_301 = \true; break;
					}
					$result = $res_298;
					$this->setPos($pos_298);
					$key = 'match_'.'Minus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_301 = \true; break;
					}
					$result = $res_298;
					$this->setPos($pos_298);
					$_301 = \false; break;
				}
				while(\false);
				if($_301 === \false) { $_303 = \false; break; }
				$_303 = \true; break;
			}
			while(\false);
			if($_303 === \false) {
				$result = $res_304;
				$this->setPos($pos_304);
				unset($res_304, $pos_304);
				break;
			}
		}
		$_305 = \true; break;
	}
	while(\false);
	if($_305 === \true) { return $this->finalise($result); }
	if($_305 === \false) { return \false; }
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
	$_312 = \null;
	do {
		$key = 'match_'.'Variable'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_312 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '=') {
			$this->addPos(1);
			$result["text"] .= '=';
		}
		else { $_312 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_312 = \false; break; }
		$_312 = \true; break;
	}
	while(\false);
	if($_312 === \true) { return $this->finalise($result); }
	if($_312 === \false) { return \false; }
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

/* Start: (VariableAssignment | Expr ) ";"? */
protected $match_Start_typestack = ['Start'];
function match_Start($stack = []) {
	$matchrule = 'Start';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_323 = \null;
	do {
		$_320 = \null;
		do {
			$_318 = \null;
			do {
				$res_315 = $result;
				$pos_315 = $this->pos;
				$key = 'match_'.'VariableAssignment'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_318 = \true; break;
				}
				$result = $res_315;
				$this->setPos($pos_315);
				$key = 'match_'.'Expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_318 = \true; break;
				}
				$result = $res_315;
				$this->setPos($pos_315);
				$_318 = \false; break;
			}
			while(\false);
			if($_318 === \false) { $_320 = \false; break; }
			$_320 = \true; break;
		}
		while(\false);
		if($_320 === \false) { $_323 = \false; break; }
		$res_322 = $result;
		$pos_322 = $this->pos;
		if (\substr($this->string, $this->pos, 1) === ';') {
			$this->addPos(1);
			$result["text"] .= ';';
		}
		else {
			$result = $res_322;
			$this->setPos($pos_322);
			unset($res_322, $pos_322);
		}
		$_323 = \true; break;
	}
	while(\false);
	if($_323 === \true) { return $this->finalise($result); }
	if($_323 === \false) { return \false; }
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

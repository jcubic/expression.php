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
        $disabled = explode(',', ini_get('disable_functions'));
        return !in_array('eval', $disabled);
    }
    private function _eval($code) {
        if (!$this->is_eval_enabled()) {
            $tmp_file = tempnam(sys_get_temp_dir(), "ccf");
            file_put_contents($tmp_file, "<?php $code ");
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
           $expr = new jcubic\__Expression();
           for ($i = 0; $i < count($params); ++$i) {
              $expr->variables[$params[$i]] = $args[$i];
           }
           return $expr->evaluate(' . json_encode($body) . ');
        };';
        $this->functions[$name] = $this->_eval($code);
    }

/* Name: (/[A-Za-z]+/ | "$" /[0-9A-Za-z]+/) */
protected $match_Name_typestack = ['Name'];
function match_Name($stack = []) {
	$matchrule = 'Name';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_8 = \null;
	do {
		$_6 = \null;
		do {
			$res_0 = $result;
			$pos_0 = $this->pos;
			if (($subres = $this->rx('/[A-Za-z]+/')) !== \false) {
				$result["text"] .= $subres;
				$_6 = \true; break;
			}
			$result = $res_0;
			$this->setPos($pos_0);
			$_4 = \null;
			do {
				if (\substr($this->string, $this->pos, 1) === '$') {
					$this->addPos(1);
					$result["text"] .= '$';
				}
				else { $_4 = \false; break; }
				if (($subres = $this->rx('/[0-9A-Za-z]+/')) !== \false) { $result["text"] .= $subres; }
				else { $_4 = \false; break; }
				$_4 = \true; break;
			}
			while(\false);
			if($_4 === \true) { $_6 = \true; break; }
			$result = $res_0;
			$this->setPos($pos_0);
			$_6 = \false; break;
		}
		while(\false);
		if($_6 === \false) { $_8 = \false; break; }
		$_8 = \true; break;
	}
	while(\false);
	if($_8 === \true) { return $this->finalise($result); }
	if($_8 === \false) { return \false; }
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
	$_24 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "q");
		if (($subres = $this->rx('/\'/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'q');
		}
		else {
			$result = \array_pop($stack);
			$_24 = \false; break;
		}
		while (\true) {
			$res_22 = $result;
			$pos_22 = $this->pos;
			$_21 = \null;
			do {
				$_19 = \null;
				do {
					$res_13 = $result;
					$pos_13 = $this->pos;
					$_16 = \null;
					do {
						while (\true) {
							$res_14 = $result;
							$pos_14 = $this->pos;
							if (($subres = $this->rx('/\\\\{2}/')) !== \false) { $result["text"] .= $subres; }
							else {
								$result = $res_14;
								$this->setPos($pos_14);
								unset($res_14, $pos_14);
								break;
							}
						}
						if (($subres = $this->rx('/\\\\\'/')) !== \false) { $result["text"] .= $subres; }
						else { $_16 = \false; break; }
						$_16 = \true; break;
					}
					while(\false);
					if($_16 === \true) { $_19 = \true; break; }
					$result = $res_13;
					$this->setPos($pos_13);
					if (($subres = $this->rx('/[^\']/')) !== \false) {
						$result["text"] .= $subres;
						$_19 = \true; break;
					}
					$result = $res_13;
					$this->setPos($pos_13);
					$_19 = \false; break;
				}
				while(\false);
				if($_19 === \false) { $_21 = \false; break; }
				$_21 = \true; break;
			}
			while(\false);
			if($_21 === \false) {
				$result = $res_22;
				$this->setPos($pos_22);
				unset($res_22, $pos_22);
				break;
			}
		}
		if (($subres = $this->literal(''.$this->expression($result, $stack, 'q').'')) !== \false) { $result["text"] .= $subres; }
		else { $_24 = \false; break; }
		$_24 = \true; break;
	}
	while(\false);
	if($_24 === \true) { return $this->finalise($result); }
	if($_24 === \false) { return \false; }
}


/* DoubleQuoted: q:/"/ ( /\\{2}/ * /\\"/ | /[^"]/ ) * '$q' */
protected $match_DoubleQuoted_typestack = ['DoubleQuoted'];
function match_DoubleQuoted($stack = []) {
	$matchrule = 'DoubleQuoted';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_38 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "q");
		if (($subres = $this->rx('/"/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'q');
		}
		else {
			$result = \array_pop($stack);
			$_38 = \false; break;
		}
		while (\true) {
			$res_36 = $result;
			$pos_36 = $this->pos;
			$_35 = \null;
			do {
				$_33 = \null;
				do {
					$res_27 = $result;
					$pos_27 = $this->pos;
					$_30 = \null;
					do {
						while (\true) {
							$res_28 = $result;
							$pos_28 = $this->pos;
							if (($subres = $this->rx('/\\\\{2}/')) !== \false) { $result["text"] .= $subres; }
							else {
								$result = $res_28;
								$this->setPos($pos_28);
								unset($res_28, $pos_28);
								break;
							}
						}
						if (($subres = $this->rx('/\\\\"/')) !== \false) { $result["text"] .= $subres; }
						else { $_30 = \false; break; }
						$_30 = \true; break;
					}
					while(\false);
					if($_30 === \true) { $_33 = \true; break; }
					$result = $res_27;
					$this->setPos($pos_27);
					if (($subres = $this->rx('/[^"]/')) !== \false) {
						$result["text"] .= $subres;
						$_33 = \true; break;
					}
					$result = $res_27;
					$this->setPos($pos_27);
					$_33 = \false; break;
				}
				while(\false);
				if($_33 === \false) { $_35 = \false; break; }
				$_35 = \true; break;
			}
			while(\false);
			if($_35 === \false) {
				$result = $res_36;
				$this->setPos($pos_36);
				unset($res_36, $pos_36);
				break;
			}
		}
		if (($subres = $this->literal(''.$this->expression($result, $stack, 'q').'')) !== \false) { $result["text"] .= $subres; }
		else { $_38 = \false; break; }
		$_38 = \true; break;
	}
	while(\false);
	if($_38 === \true) { return $this->finalise($result); }
	if($_38 === \false) { return \false; }
}


/* String: SingleQuoted | DoubleQuoted */
protected $match_String_typestack = ['String'];
function match_String($stack = []) {
	$matchrule = 'String';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_43 = \null;
	do {
		$res_40 = $result;
		$pos_40 = $this->pos;
		$key = 'match_'.'SingleQuoted'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_43 = \true; break;
		}
		$result = $res_40;
		$this->setPos($pos_40);
		$key = 'match_'.'DoubleQuoted'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_43 = \true; break;
		}
		$result = $res_40;
		$this->setPos($pos_40);
		$_43 = \false; break;
	}
	while(\false);
	if($_43 === \true) { return $this->finalise($result); }
	if($_43 === \false) { return \false; }
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
	$_52 = \null;
	do {
		$res_45 = $result;
		$pos_45 = $this->pos;
		if (($subres = $this->literal('true')) !== \false) {
			$result["text"] .= $subres;
			$_52 = \true; break;
		}
		$result = $res_45;
		$this->setPos($pos_45);
		$_50 = \null;
		do {
			$res_47 = $result;
			$pos_47 = $this->pos;
			if (($subres = $this->literal('false')) !== \false) {
				$result["text"] .= $subres;
				$_50 = \true; break;
			}
			$result = $res_47;
			$this->setPos($pos_47);
			if (($subres = $this->literal('null')) !== \false) {
				$result["text"] .= $subres;
				$_50 = \true; break;
			}
			$result = $res_47;
			$this->setPos($pos_47);
			$_50 = \false; break;
		}
		while(\false);
		if($_50 === \true) { $_52 = \true; break; }
		$result = $res_45;
		$this->setPos($pos_45);
		$_52 = \false; break;
	}
	while(\false);
	if($_52 === \true) { return $this->finalise($result); }
	if($_52 === \false) { return \false; }
}


/* RegExp: /(?<!\\\\)\/(?:[^\/]|\\\\\/)+\// /[imsxUXJ]/* */
protected $match_RegExp_typestack = ['RegExp'];
function match_RegExp($stack = []) {
	$matchrule = 'RegExp';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_56 = \null;
	do {
		if (($subres = $this->rx('/(?<!\\\\\\\\)\/(?:[^\/]|\\\\\\\\\/)+\//')) !== \false) { $result["text"] .= $subres; }
		else { $_56 = \false; break; }
		while (\true) {
			$res_55 = $result;
			$pos_55 = $this->pos;
			if (($subres = $this->rx('/[imsxUXJ]/')) !== \false) { $result["text"] .= $subres; }
			else {
				$result = $res_55;
				$this->setPos($pos_55);
				unset($res_55, $pos_55);
				break;
			}
		}
		$_56 = \true; break;
	}
	while(\false);
	if($_56 === \true) { return $this->finalise($result); }
	if($_56 === \false) { return \false; }
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
	$_100 = \null;
	do {
		$res_59 = $result;
		$pos_59 = $this->pos;
		$_62 = \null;
		do {
			$key = 'match_'.'Consts'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else { $_62 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$_62 = \true; break;
		}
		while(\false);
		if($_62 === \true) { $_100 = \true; break; }
		$result = $res_59;
		$this->setPos($pos_59);
		$_98 = \null;
		do {
			$res_64 = $result;
			$pos_64 = $this->pos;
			$_67 = \null;
			do {
				$key = 'match_'.'RegExp'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_67 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_67 = \true; break;
			}
			while(\false);
			if($_67 === \true) { $_98 = \true; break; }
			$result = $res_64;
			$this->setPos($pos_64);
			$_96 = \null;
			do {
				$res_69 = $result;
				$pos_69 = $this->pos;
				$_72 = \null;
				do {
					$key = 'match_'.'String'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) { $this->store($result, $subres); }
					else { $_72 = \false; break; }
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					$_72 = \true; break;
				}
				while(\false);
				if($_72 === \true) { $_96 = \true; break; }
				$result = $res_69;
				$this->setPos($pos_69);
				$_94 = \null;
				do {
					$res_74 = $result;
					$pos_74 = $this->pos;
					$_77 = \null;
					do {
						$key = 'match_'.'VariableReference'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) { $this->store($result, $subres); }
						else { $_77 = \false; break; }
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						$_77 = \true; break;
					}
					while(\false);
					if($_77 === \true) { $_94 = \true; break; }
					$result = $res_74;
					$this->setPos($pos_74);
					$_92 = \null;
					do {
						$res_79 = $result;
						$pos_79 = $this->pos;
						$_82 = \null;
						do {
							$key = 'match_'.'Number'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) { $this->store($result, $subres); }
							else { $_82 = \false; break; }
							if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
							$_82 = \true; break;
						}
						while(\false);
						if($_82 === \true) { $_92 = \true; break; }
						$result = $res_79;
						$this->setPos($pos_79);
						$_90 = \null;
						do {
							if (\substr($this->string, $this->pos, 1) === '(') {
								$this->addPos(1);
								$result["text"] .= '(';
							}
							else { $_90 = \false; break; }
							if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
							$key = 'match_'.'Expr'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) { $this->store($result, $subres); }
							else { $_90 = \false; break; }
							if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
							if (\substr($this->string, $this->pos, 1) === ')') {
								$this->addPos(1);
								$result["text"] .= ')';
							}
							else { $_90 = \false; break; }
							if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
							$_90 = \true; break;
						}
						while(\false);
						if($_90 === \true) { $_92 = \true; break; }
						$result = $res_79;
						$this->setPos($pos_79);
						$_92 = \false; break;
					}
					while(\false);
					if($_92 === \true) { $_94 = \true; break; }
					$result = $res_74;
					$this->setPos($pos_74);
					$_94 = \false; break;
				}
				while(\false);
				if($_94 === \true) { $_96 = \true; break; }
				$result = $res_69;
				$this->setPos($pos_69);
				$_96 = \false; break;
			}
			while(\false);
			if($_96 === \true) { $_98 = \true; break; }
			$result = $res_64;
			$this->setPos($pos_64);
			$_98 = \false; break;
		}
		while(\false);
		if($_98 === \true) { $_100 = \true; break; }
		$result = $res_59;
		$this->setPos($pos_59);
		$_100 = \false; break;
	}
	while(\false);
	if($_100 === \true) { return $this->finalise($result); }
	if($_100 === \false) { return \false; }
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
	$_115 = \null;
	do {
		$key = 'match_'.'Name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_115 = \false; break; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_115 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_111 = $result;
			$pos_111 = $this->pos;
			$_110 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'Expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_110 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$res_108 = $result;
				$pos_108 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else {
					$result = $res_108;
					$this->setPos($pos_108);
					unset($res_108, $pos_108);
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_110 = \true; break;
			}
			while(\false);
			if($_110 === \false) {
				$result = $res_111;
				$this->setPos($pos_111);
				unset($res_111, $pos_111);
				break;
			}
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_115 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_115 = \true; break;
	}
	while(\false);
	if($_115 === \true) { return $this->finalise($result); }
	if($_115 === \false) { return \false; }
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
	$_122 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
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


/* ToInt: '+' > operand:Value > */
protected $match_ToInt_typestack = ['ToInt'];
function match_ToInt($stack = []) {
	$matchrule = 'ToInt';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_128 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_128 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Value'; $pos = $this->pos;
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


/* Unary: ( FunctionCall | Negation | ToInt | Value ) */
protected $match_Unary_typestack = ['Unary'];
function match_Unary($stack = []) {
	$matchrule = 'Unary';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_143 = \null;
	do {
		$_141 = \null;
		do {
			$res_130 = $result;
			$pos_130 = $this->pos;
			$key = 'match_'.'FunctionCall'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres);
				$_141 = \true; break;
			}
			$result = $res_130;
			$this->setPos($pos_130);
			$_139 = \null;
			do {
				$res_132 = $result;
				$pos_132 = $this->pos;
				$key = 'match_'.'Negation'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_139 = \true; break;
				}
				$result = $res_132;
				$this->setPos($pos_132);
				$_137 = \null;
				do {
					$res_134 = $result;
					$pos_134 = $this->pos;
					$key = 'match_'.'ToInt'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_137 = \true; break;
					}
					$result = $res_134;
					$this->setPos($pos_134);
					$key = 'match_'.'Value'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_137 = \true; break;
					}
					$result = $res_134;
					$this->setPos($pos_134);
					$_137 = \false; break;
				}
				while(\false);
				if($_137 === \true) { $_139 = \true; break; }
				$result = $res_132;
				$this->setPos($pos_132);
				$_139 = \false; break;
			}
			while(\false);
			if($_139 === \true) { $_141 = \true; break; }
			$result = $res_130;
			$this->setPos($pos_130);
			$_141 = \false; break;
		}
		while(\false);
		if($_141 === \false) { $_143 = \false; break; }
		$_143 = \true; break;
	}
	while(\false);
	if($_143 === \true) { return $this->finalise($result); }
	if($_143 === \false) { return \false; }
}

public function Unary_Value (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Unary_FunctionCall (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Unary_ToInt (&$result, $sub) {
        $val = $sub['operand']['val'];
        if ($this->is_string($val)) {
            $val = floatval($val);
        }
        $result['val'] = $val;
    }

public function Unary_Negation (&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->validate_number('-', $object);
        $result['val'] = $this->with_type($object['value'] * -1);
    }

/* Equal: '==' > operand:Unary > */
protected $match_Equal_typestack = ['Equal'];
function match_Equal($stack = []) {
	$matchrule = 'Equal';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_149 = \null;
	do {
		if (($subres = $this->literal('==')) !== \false) { $result["text"] .= $subres; }
		else { $_149 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
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


/* Match: '=~' > operand:Unary > */
protected $match_Match_typestack = ['Match'];
function match_Match($stack = []) {
	$matchrule = 'Match';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_155 = \null;
	do {
		if (($subres = $this->literal('=~')) !== \false) { $result["text"] .= $subres; }
		else { $_155 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_155 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_155 = \true; break;
	}
	while(\false);
	if($_155 === \true) { return $this->finalise($result); }
	if($_155 === \false) { return \false; }
}


/* NotEqual: '!=' > operand:Unary > */
protected $match_NotEqual_typestack = ['NotEqual'];
function match_NotEqual($stack = []) {
	$matchrule = 'NotEqual';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_161 = \null;
	do {
		if (($subres = $this->literal('!=')) !== \false) { $result["text"] .= $subres; }
		else { $_161 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_161 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_161 = \true; break;
	}
	while(\false);
	if($_161 === \true) { return $this->finalise($result); }
	if($_161 === \false) { return \false; }
}


/* GreaterEqualThan: '>=' > operand:Unary > */
protected $match_GreaterEqualThan_typestack = ['GreaterEqualThan'];
function match_GreaterEqualThan($stack = []) {
	$matchrule = 'GreaterEqualThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_167 = \null;
	do {
		if (($subres = $this->literal('>=')) !== \false) { $result["text"] .= $subres; }
		else { $_167 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_167 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_167 = \true; break;
	}
	while(\false);
	if($_167 === \true) { return $this->finalise($result); }
	if($_167 === \false) { return \false; }
}


/* LessEqualThan: '<=' > operand:Unary > */
protected $match_LessEqualThan_typestack = ['LessEqualThan'];
function match_LessEqualThan($stack = []) {
	$matchrule = 'LessEqualThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_173 = \null;
	do {
		if (($subres = $this->literal('<=')) !== \false) { $result["text"] .= $subres; }
		else { $_173 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_173 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_173 = \true; break;
	}
	while(\false);
	if($_173 === \true) { return $this->finalise($result); }
	if($_173 === \false) { return \false; }
}


/* GreaterThan: '>' > operand:Unary > */
protected $match_GreaterThan_typestack = ['GreaterThan'];
function match_GreaterThan($stack = []) {
	$matchrule = 'GreaterThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_179 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '>') {
			$this->addPos(1);
			$result["text"] .= '>';
		}
		else { $_179 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_179 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_179 = \true; break;
	}
	while(\false);
	if($_179 === \true) { return $this->finalise($result); }
	if($_179 === \false) { return \false; }
}


/* LessThan: '<' > operand:Unary > */
protected $match_LessThan_typestack = ['LessThan'];
function match_LessThan($stack = []) {
	$matchrule = 'LessThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_185 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '<') {
			$this->addPos(1);
			$result["text"] .= '<';
		}
		else { $_185 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_185 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_185 = \true; break;
	}
	while(\false);
	if($_185 === \true) { return $this->finalise($result); }
	if($_185 === \false) { return \false; }
}


/* Compare: Unary > (Equal | Match | NotEqual | GreaterEqualThan | GreaterThan | LessEqualThan | LessThan ) * */
protected $match_Compare_typestack = ['Compare'];
function match_Compare($stack = []) {
	$matchrule = 'Compare';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_216 = \null;
	do {
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_216 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_215 = $result;
			$pos_215 = $this->pos;
			$_214 = \null;
			do {
				$_212 = \null;
				do {
					$res_189 = $result;
					$pos_189 = $this->pos;
					$key = 'match_'.'Equal'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_212 = \true; break;
					}
					$result = $res_189;
					$this->setPos($pos_189);
					$_210 = \null;
					do {
						$res_191 = $result;
						$pos_191 = $this->pos;
						$key = 'match_'.'Match'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_210 = \true; break;
						}
						$result = $res_191;
						$this->setPos($pos_191);
						$_208 = \null;
						do {
							$res_193 = $result;
							$pos_193 = $this->pos;
							$key = 'match_'.'NotEqual'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres);
								$_208 = \true; break;
							}
							$result = $res_193;
							$this->setPos($pos_193);
							$_206 = \null;
							do {
								$res_195 = $result;
								$pos_195 = $this->pos;
								$key = 'match_'.'GreaterEqualThan'; $pos = $this->pos;
								$subres = $this->packhas($key, $pos)
									? $this->packread($key, $pos)
									: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
								if ($subres !== \false) {
									$this->store($result, $subres);
									$_206 = \true; break;
								}
								$result = $res_195;
								$this->setPos($pos_195);
								$_204 = \null;
								do {
									$res_197 = $result;
									$pos_197 = $this->pos;
									$key = 'match_'.'GreaterThan'; $pos = $this->pos;
									$subres = $this->packhas($key, $pos)
										? $this->packread($key, $pos)
										: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
									if ($subres !== \false) {
										$this->store($result, $subres);
										$_204 = \true; break;
									}
									$result = $res_197;
									$this->setPos($pos_197);
									$_202 = \null;
									do {
										$res_199 = $result;
										$pos_199 = $this->pos;
										$key = 'match_'.'LessEqualThan'; $pos = $this->pos;
										$subres = $this->packhas($key, $pos)
											? $this->packread($key, $pos)
											: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
										if ($subres !== \false) {
											$this->store($result, $subres);
											$_202 = \true; break;
										}
										$result = $res_199;
										$this->setPos($pos_199);
										$key = 'match_'.'LessThan'; $pos = $this->pos;
										$subres = $this->packhas($key, $pos)
											? $this->packread($key, $pos)
											: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
										if ($subres !== \false) {
											$this->store($result, $subres);
											$_202 = \true; break;
										}
										$result = $res_199;
										$this->setPos($pos_199);
										$_202 = \false; break;
									}
									while(\false);
									if($_202 === \true) { $_204 = \true; break; }
									$result = $res_197;
									$this->setPos($pos_197);
									$_204 = \false; break;
								}
								while(\false);
								if($_204 === \true) { $_206 = \true; break; }
								$result = $res_195;
								$this->setPos($pos_195);
								$_206 = \false; break;
							}
							while(\false);
							if($_206 === \true) { $_208 = \true; break; }
							$result = $res_193;
							$this->setPos($pos_193);
							$_208 = \false; break;
						}
						while(\false);
						if($_208 === \true) { $_210 = \true; break; }
						$result = $res_191;
						$this->setPos($pos_191);
						$_210 = \false; break;
					}
					while(\false);
					if($_210 === \true) { $_212 = \true; break; }
					$result = $res_189;
					$this->setPos($pos_189);
					$_212 = \false; break;
				}
				while(\false);
				if($_212 === \false) { $_214 = \false; break; }
				$_214 = \true; break;
			}
			while(\false);
			if($_214 === \false) {
				$result = $res_215;
				$this->setPos($pos_215);
				unset($res_215, $pos_215);
				break;
			}
		}
		$_216 = \true; break;
	}
	while(\false);
	if($_216 === \true) { return $this->finalise($result); }
	if($_216 === \false) { return \false; }
}

public function Compare_Unary (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Compare_Equal (&$result, $sub) {
        $this->check_equal($result, $sub['operand']['val'], function($a, $b) {
            return $a == $b;
        });
    }

public function Compare_Match (&$result, $sub) {
        $re = $sub['operand']['val'];
        $string = $result['val'];
        $this->validate_types(['string'], '=~', $string);
        $this->validate_types(['regex'], '=~', $re);
        $value = @preg_match($re['value'], $string['value'], $match);
        if (!is_int($value)) {
            throw new Exception("Invalid regular expression: ${re['value']}");
        }
        foreach (array_keys($this->variables) as $name) {
            unset($this->variables[$name]);
        }
        for ($i = 0; $i < count($match); $i++) {
            $this->variables['$' . $i] = $match[$i];
        }
        $result['val'] = $this->with_type($value == 1);
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

/* And: "&&" > operand:Compare > */
protected $match_And_typestack = ['And'];
function match_And($stack = []) {
	$matchrule = 'And';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_222 = \null;
	do {
		if (($subres = $this->literal('&&')) !== \false) { $result["text"] .= $subres; }
		else { $_222 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_222 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_222 = \true; break;
	}
	while(\false);
	if($_222 === \true) { return $this->finalise($result); }
	if($_222 === \false) { return \false; }
}


/* Or: "||" > operand:Compare > */
protected $match_Or_typestack = ['Or'];
function match_Or($stack = []) {
	$matchrule = 'Or';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_228 = \null;
	do {
		if (($subres = $this->literal('||')) !== \false) { $result["text"] .= $subres; }
		else { $_228 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_228 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_228 = \true; break;
	}
	while(\false);
	if($_228 === \true) { return $this->finalise($result); }
	if($_228 === \false) { return \false; }
}


/* Boolean: Compare > (And | Or ) * */
protected $match_Boolean_typestack = ['Boolean'];
function match_Boolean($stack = []) {
	$matchrule = 'Boolean';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_239 = \null;
	do {
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_239 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_238 = $result;
			$pos_238 = $this->pos;
			$_237 = \null;
			do {
				$_235 = \null;
				do {
					$res_232 = $result;
					$pos_232 = $this->pos;
					$key = 'match_'.'And'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_235 = \true; break;
					}
					$result = $res_232;
					$this->setPos($pos_232);
					$key = 'match_'.'Or'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_235 = \true; break;
					}
					$result = $res_232;
					$this->setPos($pos_232);
					$_235 = \false; break;
				}
				while(\false);
				if($_235 === \false) { $_237 = \false; break; }
				$_237 = \true; break;
			}
			while(\false);
			if($_237 === \false) {
				$result = $res_238;
				$this->setPos($pos_238);
				unset($res_238, $pos_238);
				break;
			}
		}
		$_239 = \true; break;
	}
	while(\false);
	if($_239 === \true) { return $this->finalise($result); }
	if($_239 === \false) { return \false; }
}

public function Boolean_Compare (&$result, $sub) {
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
	$_261 = \null;
	do {
		$res_241 = $result;
		$pos_241 = $this->pos;
		$_244 = \null;
		do {
			$key = 'match_'.'FunctionCall'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else { $_244 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$_244 = \true; break;
		}
		while(\false);
		if($_244 === \true) { $_261 = \true; break; }
		$result = $res_241;
		$this->setPos($pos_241);
		$_259 = \null;
		do {
			$res_246 = $result;
			$pos_246 = $this->pos;
			$_249 = \null;
			do {
				$key = 'match_'.'VariableReference'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_249 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_249 = \true; break;
			}
			while(\false);
			if($_249 === \true) { $_259 = \true; break; }
			$result = $res_246;
			$this->setPos($pos_246);
			$_257 = \null;
			do {
				if (\substr($this->string, $this->pos, 1) === '(') {
					$this->addPos(1);
					$result["text"] .= '(';
				}
				else { $_257 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'Expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_257 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				if (\substr($this->string, $this->pos, 1) === ')') {
					$this->addPos(1);
					$result["text"] .= ')';
				}
				else { $_257 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_257 = \true; break;
			}
			while(\false);
			if($_257 === \true) { $_259 = \true; break; }
			$result = $res_246;
			$this->setPos($pos_246);
			$_259 = \false; break;
		}
		while(\false);
		if($_259 === \true) { $_261 = \true; break; }
		$result = $res_241;
		$this->setPos($pos_241);
		$_261 = \false; break;
	}
	while(\false);
	if($_261 === \true) { return $this->finalise($result); }
	if($_261 === \false) { return \false; }
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
	$_267 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '*') {
			$this->addPos(1);
			$result["text"] .= '*';
		}
		else { $_267 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Boolean'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_267 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_267 = \true; break;
	}
	while(\false);
	if($_267 === \true) { return $this->finalise($result); }
	if($_267 === \false) { return \false; }
}


/* Div: '/' > operand:Boolean > */
protected $match_Div_typestack = ['Div'];
function match_Div($stack = []) {
	$matchrule = 'Div';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_273 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '/') {
			$this->addPos(1);
			$result["text"] .= '/';
		}
		else { $_273 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Boolean'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_273 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_273 = \true; break;
	}
	while(\false);
	if($_273 === \true) { return $this->finalise($result); }
	if($_273 === \false) { return \false; }
}


/* Mod: '%' > operand:Boolean > */
protected $match_Mod_typestack = ['Mod'];
function match_Mod($stack = []) {
	$matchrule = 'Mod';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_279 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '%') {
			$this->addPos(1);
			$result["text"] .= '%';
		}
		else { $_279 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Boolean'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_279 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_279 = \true; break;
	}
	while(\false);
	if($_279 === \true) { return $this->finalise($result); }
	if($_279 === \false) { return \false; }
}


/* Product: Boolean > ( Times | ImplicitTimes | Div | Mod ) * */
protected $match_Product_typestack = ['Product'];
function match_Product($stack = []) {
	$matchrule = 'Product';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_298 = \null;
	do {
		$key = 'match_'.'Boolean'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_298 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_297 = $result;
			$pos_297 = $this->pos;
			$_296 = \null;
			do {
				$_294 = \null;
				do {
					$res_283 = $result;
					$pos_283 = $this->pos;
					$key = 'match_'.'Times'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_294 = \true; break;
					}
					$result = $res_283;
					$this->setPos($pos_283);
					$_292 = \null;
					do {
						$res_285 = $result;
						$pos_285 = $this->pos;
						$key = 'match_'.'ImplicitTimes'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_292 = \true; break;
						}
						$result = $res_285;
						$this->setPos($pos_285);
						$_290 = \null;
						do {
							$res_287 = $result;
							$pos_287 = $this->pos;
							$key = 'match_'.'Div'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres);
								$_290 = \true; break;
							}
							$result = $res_287;
							$this->setPos($pos_287);
							$key = 'match_'.'Mod'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres);
								$_290 = \true; break;
							}
							$result = $res_287;
							$this->setPos($pos_287);
							$_290 = \false; break;
						}
						while(\false);
						if($_290 === \true) { $_292 = \true; break; }
						$result = $res_285;
						$this->setPos($pos_285);
						$_292 = \false; break;
					}
					while(\false);
					if($_292 === \true) { $_294 = \true; break; }
					$result = $res_283;
					$this->setPos($pos_283);
					$_294 = \false; break;
				}
				while(\false);
				if($_294 === \false) { $_296 = \false; break; }
				$_296 = \true; break;
			}
			while(\false);
			if($_296 === \false) {
				$result = $res_297;
				$this->setPos($pos_297);
				unset($res_297, $pos_297);
				break;
			}
		}
		$_298 = \true; break;
	}
	while(\false);
	if($_298 === \true) { return $this->finalise($result); }
	if($_298 === \false) { return \false; }
}

public function Product_Boolean (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Product_ImplicitTimes (&$result, $sub) {
        $object = $sub['val'];
        $this->validate_number('*', $object);
        $this->validate_number('*', $result['val']);
        $result['val'] = $this->with_type($result['val']['value'] * $object['value']);
    }

public function Product_Times (&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->validate_number('*', $object);
        $this->validate_number('*', $result['val']);
        $result['val'] = $this->with_type($result['val']['value'] * $object['value']);
    }

public function Product_Div (&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->validate_number('/', $object);
        $this->validate_number('/', $result['val']);
        $result['val'] = $this->with_type($result['val']['value'] / $object['value']);
    }

public function Product_Mod (&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->validate_number('%', $object);
        $this->validate_number('%', $result['val']);
        $result['val'] = $this->with_type($result['val']['value'] % $object['value']);
    }

/* Plus: '+' > operand:Product > */
protected $match_Plus_typestack = ['Plus'];
function match_Plus($stack = []) {
	$matchrule = 'Plus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_304 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_304 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_304 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_304 = \true; break;
	}
	while(\false);
	if($_304 === \true) { return $this->finalise($result); }
	if($_304 === \false) { return \false; }
}


/* Minus: '-' > operand:Product > */
protected $match_Minus_typestack = ['Minus'];
function match_Minus($stack = []) {
	$matchrule = 'Minus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_310 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_310 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_310 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_310 = \true; break;
	}
	while(\false);
	if($_310 === \true) { return $this->finalise($result); }
	if($_310 === \false) { return \false; }
}


/* Sum: Product > ( Plus | Minus ) * */
protected $match_Sum_typestack = ['Sum'];
function match_Sum($stack = []) {
	$matchrule = 'Sum';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_321 = \null;
	do {
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_321 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_320 = $result;
			$pos_320 = $this->pos;
			$_319 = \null;
			do {
				$_317 = \null;
				do {
					$res_314 = $result;
					$pos_314 = $this->pos;
					$key = 'match_'.'Plus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_317 = \true; break;
					}
					$result = $res_314;
					$this->setPos($pos_314);
					$key = 'match_'.'Minus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_317 = \true; break;
					}
					$result = $res_314;
					$this->setPos($pos_314);
					$_317 = \false; break;
				}
				while(\false);
				if($_317 === \false) { $_319 = \false; break; }
				$_319 = \true; break;
			}
			while(\false);
			if($_319 === \false) {
				$result = $res_320;
				$this->setPos($pos_320);
				unset($res_320, $pos_320);
				break;
			}
		}
		$_321 = \true; break;
	}
	while(\false);
	if($_321 === \true) { return $this->finalise($result); }
	if($_321 === \false) { return \false; }
}

public function Sum_Product (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Sum_Plus (&$result, $sub) {
        $object = $sub['operand']['val'];
        if ($this->is_string($object)) {
            $result['val'] = $this->with_type($result['val']['value'] . $object['value']);
        } else {
            $this->validate_number('+', $object);
            $this->validate_number('+', $result['val']);
            $result['val'] = $this->with_type($result['val']['value'] + $object['value']);
        }
    }

public function Sum_Minus (&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->validate_number('-', $object);
        $this->validate_number('-', $result['val']);
        $result['val'] = $this->with_type($result['val']['value'] - $object['value']);
    }

/* VariableAssignment: Variable > "=" > Expr */
protected $match_VariableAssignment_typestack = ['VariableAssignment'];
function match_VariableAssignment($stack = []) {
	$matchrule = 'VariableAssignment';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_328 = \null;
	do {
		$key = 'match_'.'Variable'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_328 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '=') {
			$this->addPos(1);
			$result["text"] .= '=';
		}
		else { $_328 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_328 = \false; break; }
		$_328 = \true; break;
	}
	while(\false);
	if($_328 === \true) { return $this->finalise($result); }
	if($_328 === \false) { return \false; }
}

public function VariableAssignment_Variable (&$result, $sub) {
        $result['val'] = ["name" => $sub['val']];
    }

public function VariableAssignment_Expr (&$result, $sub) {
        $result['val']['value'] = $sub['val'];
    }

/* FunctionBody: /.+/ */
protected $match_FunctionBody_typestack = ['FunctionBody'];
function match_FunctionBody($stack = []) {
	$matchrule = 'FunctionBody';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/.+/')) !== \false) {
		$result["text"] .= $subres;
		return $this->finalise($result);
	}
	else { return \false; }
}


/* FunctionAssignment: Name "(" > ( > Variable > ","? > ) * ")" > "=" > FunctionBody */
protected $match_FunctionAssignment_typestack = ['FunctionAssignment'];
function match_FunctionAssignment($stack = []) {
	$matchrule = 'FunctionAssignment';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_346 = \null;
	do {
		$key = 'match_'.'Name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_346 = \false; break; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_346 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_340 = $result;
			$pos_340 = $this->pos;
			$_339 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'Variable'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_339 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$res_337 = $result;
				$pos_337 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else {
					$result = $res_337;
					$this->setPos($pos_337);
					unset($res_337, $pos_337);
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_339 = \true; break;
			}
			while(\false);
			if($_339 === \false) {
				$result = $res_340;
				$this->setPos($pos_340);
				unset($res_340, $pos_340);
				break;
			}
		}
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_346 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '=') {
			$this->addPos(1);
			$result["text"] .= '=';
		}
		else { $_346 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'FunctionBody'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_346 = \false; break; }
		$_346 = \true; break;
	}
	while(\false);
	if($_346 === \true) { return $this->finalise($result); }
	if($_346 === \false) { return \false; }
}

public function FunctionAssignment_Name (&$result, $sub) {
        $name = $sub['text'];
        $result['val'] = [
            "params" => [],
            "name" => $name,
            "body" => null
        ];
    }

public function FunctionAssignment_Variable (&$result, $sub) {
        array_push($result['val']['params'], $sub['val']);
    }

public function FunctionAssignment_FunctionBody (&$result, $sub) {
       $result['val']['body'] = $sub['text'];
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

/* Start: (VariableAssignment | FunctionAssignment | Expr ) ";"? */
protected $match_Start_typestack = ['Start'];
function match_Start($stack = []) {
	$matchrule = 'Start';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_361 = \null;
	do {
		$_358 = \null;
		do {
			$_356 = \null;
			do {
				$res_349 = $result;
				$pos_349 = $this->pos;
				$key = 'match_'.'VariableAssignment'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_356 = \true; break;
				}
				$result = $res_349;
				$this->setPos($pos_349);
				$_354 = \null;
				do {
					$res_351 = $result;
					$pos_351 = $this->pos;
					$key = 'match_'.'FunctionAssignment'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_354 = \true; break;
					}
					$result = $res_351;
					$this->setPos($pos_351);
					$key = 'match_'.'Expr'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_354 = \true; break;
					}
					$result = $res_351;
					$this->setPos($pos_351);
					$_354 = \false; break;
				}
				while(\false);
				if($_354 === \true) { $_356 = \true; break; }
				$result = $res_349;
				$this->setPos($pos_349);
				$_356 = \false; break;
			}
			while(\false);
			if($_356 === \false) { $_358 = \false; break; }
			$_358 = \true; break;
		}
		while(\false);
		if($_358 === \false) { $_361 = \false; break; }
		$res_360 = $result;
		$pos_360 = $this->pos;
		if (\substr($this->string, $this->pos, 1) === ';') {
			$this->addPos(1);
			$result["text"] .= ';';
		}
		else {
			$result = $res_360;
			$this->setPos($pos_360);
			unset($res_360, $pos_360);
		}
		$_361 = \true; break;
	}
	while(\false);
	if($_361 === \true) { return $this->finalise($result); }
	if($_361 === \false) { return \false; }
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

public function Start_FunctionAssignment (&$result, $sub) {
        $this->make_function($sub['val']);
        $result['val'] = true;
    }

public function Start_Expr (&$result, $sub) {
        $result['val'] = $sub['val'];
    }



}

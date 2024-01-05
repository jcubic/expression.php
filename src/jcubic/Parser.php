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

/*

TODO: JSON objects
      Property Access / square brackets
      hex, binary, and octal values
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
        // ref: https://stackoverflow.com/a/25158401/387194
        $disabled = explode(',', ini_get('disable_functions'));
        return !in_array('eval', $disabled);
    }
    private function _eval($code) {
        if (!$this->is_eval_enabled()) {
            // ref: https://stackoverflow.com/a/52689881/387194
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
    private function shift($operation, $left, $right, $fn) {
        $this->validate_number($operation, $left);
        $this->validate_number($operation, $right);
        return $this->with_type($fn($left['value'], $right['value']));
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

/* Negative: '-' > operand:Value > */
protected $match_Negative_typestack = ['Negative'];
function match_Negative($stack = []) {
	$matchrule = 'Negative';
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


/* Negation: '!' > operand:Value > */
protected $match_Negation_typestack = ['Negation'];
function match_Negation($stack = []) {
	$matchrule = 'Negation';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_134 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '!') {
			$this->addPos(1);
			$result["text"] .= '!';
		}
		else { $_134 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_134 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_134 = \true; break;
	}
	while(\false);
	if($_134 === \true) { return $this->finalise($result); }
	if($_134 === \false) { return \false; }
}


/* Unary: ( FunctionCall | Negation | Negative | ToInt | Value ) */
protected $match_Unary_typestack = ['Unary'];
function match_Unary($stack = []) {
	$matchrule = 'Unary';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_153 = \null;
	do {
		$_151 = \null;
		do {
			$res_136 = $result;
			$pos_136 = $this->pos;
			$key = 'match_'.'FunctionCall'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres);
				$_151 = \true; break;
			}
			$result = $res_136;
			$this->setPos($pos_136);
			$_149 = \null;
			do {
				$res_138 = $result;
				$pos_138 = $this->pos;
				$key = 'match_'.'Negation'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_149 = \true; break;
				}
				$result = $res_138;
				$this->setPos($pos_138);
				$_147 = \null;
				do {
					$res_140 = $result;
					$pos_140 = $this->pos;
					$key = 'match_'.'Negative'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_147 = \true; break;
					}
					$result = $res_140;
					$this->setPos($pos_140);
					$_145 = \null;
					do {
						$res_142 = $result;
						$pos_142 = $this->pos;
						$key = 'match_'.'ToInt'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_145 = \true; break;
						}
						$result = $res_142;
						$this->setPos($pos_142);
						$key = 'match_'.'Value'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_145 = \true; break;
						}
						$result = $res_142;
						$this->setPos($pos_142);
						$_145 = \false; break;
					}
					while(\false);
					if($_145 === \true) { $_147 = \true; break; }
					$result = $res_140;
					$this->setPos($pos_140);
					$_147 = \false; break;
				}
				while(\false);
				if($_147 === \true) { $_149 = \true; break; }
				$result = $res_138;
				$this->setPos($pos_138);
				$_149 = \false; break;
			}
			while(\false);
			if($_149 === \true) { $_151 = \true; break; }
			$result = $res_136;
			$this->setPos($pos_136);
			$_151 = \false; break;
		}
		while(\false);
		if($_151 === \false) { $_153 = \false; break; }
		$_153 = \true; break;
	}
	while(\false);
	if($_153 === \true) { return $this->finalise($result); }
	if($_153 === \false) { return \false; }
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

public function Unary_Negative (&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->validate_number('-', $object);
        $result['val'] = $this->with_type($object['value'] * -1);
    }

public function Unary_Negation (&$result, $sub) {
        $object = $sub['operand']['val'];
        $result['val'] = $this->with_type(!$object['value']);
    }

/* PowerOp: op:('^' | '**') > operand:Unary > */
protected $match_PowerOp_typestack = ['PowerOp'];
function match_PowerOp($stack = []) {
	$matchrule = 'PowerOp';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_165 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "op");
		$_160 = \null;
		do {
			$_158 = \null;
			do {
				$res_155 = $result;
				$pos_155 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === '^') {
					$this->addPos(1);
					$result["text"] .= '^';
					$_158 = \true; break;
				}
				$result = $res_155;
				$this->setPos($pos_155);
				if (($subres = $this->literal('**')) !== \false) {
					$result["text"] .= $subres;
					$_158 = \true; break;
				}
				$result = $res_155;
				$this->setPos($pos_155);
				$_158 = \false; break;
			}
			while(\false);
			if($_158 === \false) { $_160 = \false; break; }
			$_160 = \true; break;
		}
		while(\false);
		if($_160 === \true) {
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'op');
		}
		if($_160 === \false) {
			$result = \array_pop($stack);
			$_165 = \false; break;
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_165 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_165 = \true; break;
	}
	while(\false);
	if($_165 === \true) { return $this->finalise($result); }
	if($_165 === \false) { return \false; }
}


/* Power: Unary > PowerOp * */
protected $match_Power_typestack = ['Power'];
function match_Power($stack = []) {
	$matchrule = 'Power';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_170 = \null;
	do {
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_170 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_169 = $result;
			$pos_169 = $this->pos;
			$key = 'match_'.'PowerOp'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else {
				$result = $res_169;
				$this->setPos($pos_169);
				unset($res_169, $pos_169);
				break;
			}
		}
		$_170 = \true; break;
	}
	while(\false);
	if($_170 === \true) { return $this->finalise($result); }
	if($_170 === \false) { return \false; }
}

public function Power_Unary (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Power_PowerOp (&$result, $sub) {
        $object = $sub['operand']['val'];
        $op = $sub['op']['text'];
        $this->validate_number($op, $object);
        $this->validate_number($op, $result['val']);
        $result['val'] = $this->with_type(pow($result['val']['value'],  $object['value']));
    }

/* ImplicitTimes: FunctionCall > | VariableReference > | '(' > Expr > ')' > */
protected $match_ImplicitTimes_typestack = ['ImplicitTimes'];
function match_ImplicitTimes($stack = []) {
	$matchrule = 'ImplicitTimes';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_192 = \null;
	do {
		$res_172 = $result;
		$pos_172 = $this->pos;
		$_175 = \null;
		do {
			$key = 'match_'.'FunctionCall'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else { $_175 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$_175 = \true; break;
		}
		while(\false);
		if($_175 === \true) { $_192 = \true; break; }
		$result = $res_172;
		$this->setPos($pos_172);
		$_190 = \null;
		do {
			$res_177 = $result;
			$pos_177 = $this->pos;
			$_180 = \null;
			do {
				$key = 'match_'.'VariableReference'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_180 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_180 = \true; break;
			}
			while(\false);
			if($_180 === \true) { $_190 = \true; break; }
			$result = $res_177;
			$this->setPos($pos_177);
			$_188 = \null;
			do {
				if (\substr($this->string, $this->pos, 1) === '(') {
					$this->addPos(1);
					$result["text"] .= '(';
				}
				else { $_188 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'Expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_188 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				if (\substr($this->string, $this->pos, 1) === ')') {
					$this->addPos(1);
					$result["text"] .= ')';
				}
				else { $_188 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_188 = \true; break;
			}
			while(\false);
			if($_188 === \true) { $_190 = \true; break; }
			$result = $res_177;
			$this->setPos($pos_177);
			$_190 = \false; break;
		}
		while(\false);
		if($_190 === \true) { $_192 = \true; break; }
		$result = $res_172;
		$this->setPos($pos_172);
		$_192 = \false; break;
	}
	while(\false);
	if($_192 === \true) { return $this->finalise($result); }
	if($_192 === \false) { return \false; }
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

/* Times: '*' > operand:Power > */
protected $match_Times_typestack = ['Times'];
function match_Times($stack = []) {
	$matchrule = 'Times';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_198 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '*') {
			$this->addPos(1);
			$result["text"] .= '*';
		}
		else { $_198 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Power'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_198 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_198 = \true; break;
	}
	while(\false);
	if($_198 === \true) { return $this->finalise($result); }
	if($_198 === \false) { return \false; }
}


/* Div: '/' > operand:Power > */
protected $match_Div_typestack = ['Div'];
function match_Div($stack = []) {
	$matchrule = 'Div';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_204 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '/') {
			$this->addPos(1);
			$result["text"] .= '/';
		}
		else { $_204 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Power'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_204 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_204 = \true; break;
	}
	while(\false);
	if($_204 === \true) { return $this->finalise($result); }
	if($_204 === \false) { return \false; }
}


/* Mod: '%' > operand:Power > */
protected $match_Mod_typestack = ['Mod'];
function match_Mod($stack = []) {
	$matchrule = 'Mod';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_210 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '%') {
			$this->addPos(1);
			$result["text"] .= '%';
		}
		else { $_210 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Power'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_210 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_210 = \true; break;
	}
	while(\false);
	if($_210 === \true) { return $this->finalise($result); }
	if($_210 === \false) { return \false; }
}


/* Product: Power > ( Times | ImplicitTimes | Div | Mod ) * */
protected $match_Product_typestack = ['Product'];
function match_Product($stack = []) {
	$matchrule = 'Product';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_229 = \null;
	do {
		$key = 'match_'.'Power'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_229 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_228 = $result;
			$pos_228 = $this->pos;
			$_227 = \null;
			do {
				$_225 = \null;
				do {
					$res_214 = $result;
					$pos_214 = $this->pos;
					$key = 'match_'.'Times'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_225 = \true; break;
					}
					$result = $res_214;
					$this->setPos($pos_214);
					$_223 = \null;
					do {
						$res_216 = $result;
						$pos_216 = $this->pos;
						$key = 'match_'.'ImplicitTimes'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_223 = \true; break;
						}
						$result = $res_216;
						$this->setPos($pos_216);
						$_221 = \null;
						do {
							$res_218 = $result;
							$pos_218 = $this->pos;
							$key = 'match_'.'Div'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres);
								$_221 = \true; break;
							}
							$result = $res_218;
							$this->setPos($pos_218);
							$key = 'match_'.'Mod'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres);
								$_221 = \true; break;
							}
							$result = $res_218;
							$this->setPos($pos_218);
							$_221 = \false; break;
						}
						while(\false);
						if($_221 === \true) { $_223 = \true; break; }
						$result = $res_216;
						$this->setPos($pos_216);
						$_223 = \false; break;
					}
					while(\false);
					if($_223 === \true) { $_225 = \true; break; }
					$result = $res_214;
					$this->setPos($pos_214);
					$_225 = \false; break;
				}
				while(\false);
				if($_225 === \false) { $_227 = \false; break; }
				$_227 = \true; break;
			}
			while(\false);
			if($_227 === \false) {
				$result = $res_228;
				$this->setPos($pos_228);
				unset($res_228, $pos_228);
				break;
			}
		}
		$_229 = \true; break;
	}
	while(\false);
	if($_229 === \true) { return $this->finalise($result); }
	if($_229 === \false) { return \false; }
}

public function Product_Boolean (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Product_Power (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Product_Times (&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->validate_number('*', $object);
        $this->validate_number('*', $result['val']);
        $result['val'] = $this->with_type($result['val']['value'] * $object['value']);
    }

public function Product_ImplicitTimes (&$result, $sub) {
        $object = $sub['val'];
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
	$_235 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_235 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_235 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_235 = \true; break;
	}
	while(\false);
	if($_235 === \true) { return $this->finalise($result); }
	if($_235 === \false) { return \false; }
}


/* Minus: '-' > operand:Product > */
protected $match_Minus_typestack = ['Minus'];
function match_Minus($stack = []) {
	$matchrule = 'Minus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_241 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_241 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_241 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_241 = \true; break;
	}
	while(\false);
	if($_241 === \true) { return $this->finalise($result); }
	if($_241 === \false) { return \false; }
}


/* Sum: Product > ( Plus | Minus ) * */
protected $match_Sum_typestack = ['Sum'];
function match_Sum($stack = []) {
	$matchrule = 'Sum';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_252 = \null;
	do {
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_252 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_251 = $result;
			$pos_251 = $this->pos;
			$_250 = \null;
			do {
				$_248 = \null;
				do {
					$res_245 = $result;
					$pos_245 = $this->pos;
					$key = 'match_'.'Plus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_248 = \true; break;
					}
					$result = $res_245;
					$this->setPos($pos_245);
					$key = 'match_'.'Minus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_248 = \true; break;
					}
					$result = $res_245;
					$this->setPos($pos_245);
					$_248 = \false; break;
				}
				while(\false);
				if($_248 === \false) { $_250 = \false; break; }
				$_250 = \true; break;
			}
			while(\false);
			if($_250 === \false) {
				$result = $res_251;
				$this->setPos($pos_251);
				unset($res_251, $pos_251);
				break;
			}
		}
		$_252 = \true; break;
	}
	while(\false);
	if($_252 === \true) { return $this->finalise($result); }
	if($_252 === \false) { return \false; }
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
	$_259 = \null;
	do {
		$key = 'match_'.'Variable'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_259 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '=') {
			$this->addPos(1);
			$result["text"] .= '=';
		}
		else { $_259 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_259 = \false; break; }
		$_259 = \true; break;
	}
	while(\false);
	if($_259 === \true) { return $this->finalise($result); }
	if($_259 === \false) { return \false; }
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
	$_277 = \null;
	do {
		$key = 'match_'.'Name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_277 = \false; break; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_277 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_271 = $result;
			$pos_271 = $this->pos;
			$_270 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'Variable'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_270 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$res_268 = $result;
				$pos_268 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else {
					$result = $res_268;
					$this->setPos($pos_268);
					unset($res_268, $pos_268);
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_270 = \true; break;
			}
			while(\false);
			if($_270 === \false) {
				$result = $res_271;
				$this->setPos($pos_271);
				unset($res_271, $pos_271);
				break;
			}
		}
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_277 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '=') {
			$this->addPos(1);
			$result["text"] .= '=';
		}
		else { $_277 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'FunctionBody'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_277 = \false; break; }
		$_277 = \true; break;
	}
	while(\false);
	if($_277 === \true) { return $this->finalise($result); }
	if($_277 === \false) { return \false; }
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

/* ShiftLeft: '<<' > operand:Sum > */
protected $match_ShiftLeft_typestack = ['ShiftLeft'];
function match_ShiftLeft($stack = []) {
	$matchrule = 'ShiftLeft';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_283 = \null;
	do {
		if (($subres = $this->literal('<<')) !== \false) { $result["text"] .= $subres; }
		else { $_283 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Sum'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_283 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_283 = \true; break;
	}
	while(\false);
	if($_283 === \true) { return $this->finalise($result); }
	if($_283 === \false) { return \false; }
}


/* ShiftRight: '>>' > operand:Sum > */
protected $match_ShiftRight_typestack = ['ShiftRight'];
function match_ShiftRight($stack = []) {
	$matchrule = 'ShiftRight';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_289 = \null;
	do {
		if (($subres = $this->literal('>>')) !== \false) { $result["text"] .= $subres; }
		else { $_289 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Sum'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_289 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_289 = \true; break;
	}
	while(\false);
	if($_289 === \true) { return $this->finalise($result); }
	if($_289 === \false) { return \false; }
}


/* BitShift: Sum > (ShiftRight | ShiftLeft) * */
protected $match_BitShift_typestack = ['BitShift'];
function match_BitShift($stack = []) {
	$matchrule = 'BitShift';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_300 = \null;
	do {
		$key = 'match_'.'Sum'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_300 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_299 = $result;
			$pos_299 = $this->pos;
			$_298 = \null;
			do {
				$_296 = \null;
				do {
					$res_293 = $result;
					$pos_293 = $this->pos;
					$key = 'match_'.'ShiftRight'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_296 = \true; break;
					}
					$result = $res_293;
					$this->setPos($pos_293);
					$key = 'match_'.'ShiftLeft'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_296 = \true; break;
					}
					$result = $res_293;
					$this->setPos($pos_293);
					$_296 = \false; break;
				}
				while(\false);
				if($_296 === \false) { $_298 = \false; break; }
				$_298 = \true; break;
			}
			while(\false);
			if($_298 === \false) {
				$result = $res_299;
				$this->setPos($pos_299);
				unset($res_299, $pos_299);
				break;
			}
		}
		$_300 = \true; break;
	}
	while(\false);
	if($_300 === \true) { return $this->finalise($result); }
	if($_300 === \false) { return \false; }
}

public function BitShift_Sum (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function BitShift_ShiftLeft (&$result, $sub) {
        $result['val'] = $this->shift('<<', $result['val'], $sub['operand']['val'], function($a, $b) {
            return $a << $b;
        });
    }

public function BitShift_ShiftRight (&$result, $sub) {
        $result['val'] = $this->shift('>>', $result['val'], $sub['operand']['val'], function($a, $b) {
            return $a >> $b;
        });
    }

/* StrictEqual: '===' > operand:BitShift > */
protected $match_StrictEqual_typestack = ['StrictEqual'];
function match_StrictEqual($stack = []) {
	$matchrule = 'StrictEqual';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_306 = \null;
	do {
		if (($subres = $this->literal('===')) !== \false) { $result["text"] .= $subres; }
		else { $_306 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_306 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_306 = \true; break;
	}
	while(\false);
	if($_306 === \true) { return $this->finalise($result); }
	if($_306 === \false) { return \false; }
}


/* StrictNotEqual: '!==' > operand:BitShift > */
protected $match_StrictNotEqual_typestack = ['StrictNotEqual'];
function match_StrictNotEqual($stack = []) {
	$matchrule = 'StrictNotEqual';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_312 = \null;
	do {
		if (($subres = $this->literal('!==')) !== \false) { $result["text"] .= $subres; }
		else { $_312 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_312 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_312 = \true; break;
	}
	while(\false);
	if($_312 === \true) { return $this->finalise($result); }
	if($_312 === \false) { return \false; }
}


/* Equal: '==' > operand:BitShift > */
protected $match_Equal_typestack = ['Equal'];
function match_Equal($stack = []) {
	$matchrule = 'Equal';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_318 = \null;
	do {
		if (($subres = $this->literal('==')) !== \false) { $result["text"] .= $subres; }
		else { $_318 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_318 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_318 = \true; break;
	}
	while(\false);
	if($_318 === \true) { return $this->finalise($result); }
	if($_318 === \false) { return \false; }
}


/* Match: '=~' > operand:BitShift > */
protected $match_Match_typestack = ['Match'];
function match_Match($stack = []) {
	$matchrule = 'Match';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_324 = \null;
	do {
		if (($subres = $this->literal('=~')) !== \false) { $result["text"] .= $subres; }
		else { $_324 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_324 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_324 = \true; break;
	}
	while(\false);
	if($_324 === \true) { return $this->finalise($result); }
	if($_324 === \false) { return \false; }
}


/* NotEqual: '!=' > operand:BitShift > */
protected $match_NotEqual_typestack = ['NotEqual'];
function match_NotEqual($stack = []) {
	$matchrule = 'NotEqual';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_330 = \null;
	do {
		if (($subres = $this->literal('!=')) !== \false) { $result["text"] .= $subres; }
		else { $_330 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_330 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_330 = \true; break;
	}
	while(\false);
	if($_330 === \true) { return $this->finalise($result); }
	if($_330 === \false) { return \false; }
}


/* GreaterEqualThan: '>=' > operand:BitShift > */
protected $match_GreaterEqualThan_typestack = ['GreaterEqualThan'];
function match_GreaterEqualThan($stack = []) {
	$matchrule = 'GreaterEqualThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_336 = \null;
	do {
		if (($subres = $this->literal('>=')) !== \false) { $result["text"] .= $subres; }
		else { $_336 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_336 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_336 = \true; break;
	}
	while(\false);
	if($_336 === \true) { return $this->finalise($result); }
	if($_336 === \false) { return \false; }
}


/* LessEqualThan: '<=' > operand:BitShift > */
protected $match_LessEqualThan_typestack = ['LessEqualThan'];
function match_LessEqualThan($stack = []) {
	$matchrule = 'LessEqualThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_342 = \null;
	do {
		if (($subres = $this->literal('<=')) !== \false) { $result["text"] .= $subres; }
		else { $_342 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_342 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_342 = \true; break;
	}
	while(\false);
	if($_342 === \true) { return $this->finalise($result); }
	if($_342 === \false) { return \false; }
}


/* GreaterThan: '>' > operand:BitShift > */
protected $match_GreaterThan_typestack = ['GreaterThan'];
function match_GreaterThan($stack = []) {
	$matchrule = 'GreaterThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_348 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '>') {
			$this->addPos(1);
			$result["text"] .= '>';
		}
		else { $_348 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_348 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_348 = \true; break;
	}
	while(\false);
	if($_348 === \true) { return $this->finalise($result); }
	if($_348 === \false) { return \false; }
}


/* LessThan: '<' > operand:BitShift > */
protected $match_LessThan_typestack = ['LessThan'];
function match_LessThan($stack = []) {
	$matchrule = 'LessThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_354 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '<') {
			$this->addPos(1);
			$result["text"] .= '<';
		}
		else { $_354 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_354 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_354 = \true; break;
	}
	while(\false);
	if($_354 === \true) { return $this->finalise($result); }
	if($_354 === \false) { return \false; }
}


/* Compare: BitShift > (StrictEqual | Equal | Match | StrictNotEqual | NotEqual | GreaterEqualThan | GreaterThan | LessEqualThan | LessThan ) * */
protected $match_Compare_typestack = ['Compare'];
function match_Compare($stack = []) {
	$matchrule = 'Compare';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_393 = \null;
	do {
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_393 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_392 = $result;
			$pos_392 = $this->pos;
			$_391 = \null;
			do {
				$_389 = \null;
				do {
					$res_358 = $result;
					$pos_358 = $this->pos;
					$key = 'match_'.'StrictEqual'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_389 = \true; break;
					}
					$result = $res_358;
					$this->setPos($pos_358);
					$_387 = \null;
					do {
						$res_360 = $result;
						$pos_360 = $this->pos;
						$key = 'match_'.'Equal'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_387 = \true; break;
						}
						$result = $res_360;
						$this->setPos($pos_360);
						$_385 = \null;
						do {
							$res_362 = $result;
							$pos_362 = $this->pos;
							$key = 'match_'.'Match'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres);
								$_385 = \true; break;
							}
							$result = $res_362;
							$this->setPos($pos_362);
							$_383 = \null;
							do {
								$res_364 = $result;
								$pos_364 = $this->pos;
								$key = 'match_'.'StrictNotEqual'; $pos = $this->pos;
								$subres = $this->packhas($key, $pos)
									? $this->packread($key, $pos)
									: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
								if ($subres !== \false) {
									$this->store($result, $subres);
									$_383 = \true; break;
								}
								$result = $res_364;
								$this->setPos($pos_364);
								$_381 = \null;
								do {
									$res_366 = $result;
									$pos_366 = $this->pos;
									$key = 'match_'.'NotEqual'; $pos = $this->pos;
									$subres = $this->packhas($key, $pos)
										? $this->packread($key, $pos)
										: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
									if ($subres !== \false) {
										$this->store($result, $subres);
										$_381 = \true; break;
									}
									$result = $res_366;
									$this->setPos($pos_366);
									$_379 = \null;
									do {
										$res_368 = $result;
										$pos_368 = $this->pos;
										$key = 'match_'.'GreaterEqualThan'; $pos = $this->pos;
										$subres = $this->packhas($key, $pos)
											? $this->packread($key, $pos)
											: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
										if ($subres !== \false) {
											$this->store($result, $subres);
											$_379 = \true; break;
										}
										$result = $res_368;
										$this->setPos($pos_368);
										$_377 = \null;
										do {
											$res_370 = $result;
											$pos_370 = $this->pos;
											$key = 'match_'.'GreaterThan'; $pos = $this->pos;
											$subres = $this->packhas($key, $pos)
												? $this->packread($key, $pos)
												: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
											if ($subres !== \false) {
												$this->store($result, $subres);
												$_377 = \true; break;
											}
											$result = $res_370;
											$this->setPos($pos_370);
											$_375 = \null;
											do {
												$res_372 = $result;
												$pos_372 = $this->pos;
												$key = 'match_'.'LessEqualThan'; $pos = $this->pos;
												$subres = $this->packhas($key, $pos)
													? $this->packread($key, $pos)
													: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
												if ($subres !== \false) {
													$this->store($result, $subres);
													$_375 = \true; break;
												}
												$result = $res_372;
												$this->setPos($pos_372);
												$key = 'match_'.'LessThan'; $pos = $this->pos;
												$subres = $this->packhas($key, $pos)
													? $this->packread($key, $pos)
													: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
												if ($subres !== \false) {
													$this->store($result, $subres);
													$_375 = \true; break;
												}
												$result = $res_372;
												$this->setPos($pos_372);
												$_375 = \false; break;
											}
											while(\false);
											if($_375 === \true) { $_377 = \true; break; }
											$result = $res_370;
											$this->setPos($pos_370);
											$_377 = \false; break;
										}
										while(\false);
										if($_377 === \true) { $_379 = \true; break; }
										$result = $res_368;
										$this->setPos($pos_368);
										$_379 = \false; break;
									}
									while(\false);
									if($_379 === \true) { $_381 = \true; break; }
									$result = $res_366;
									$this->setPos($pos_366);
									$_381 = \false; break;
								}
								while(\false);
								if($_381 === \true) { $_383 = \true; break; }
								$result = $res_364;
								$this->setPos($pos_364);
								$_383 = \false; break;
							}
							while(\false);
							if($_383 === \true) { $_385 = \true; break; }
							$result = $res_362;
							$this->setPos($pos_362);
							$_385 = \false; break;
						}
						while(\false);
						if($_385 === \true) { $_387 = \true; break; }
						$result = $res_360;
						$this->setPos($pos_360);
						$_387 = \false; break;
					}
					while(\false);
					if($_387 === \true) { $_389 = \true; break; }
					$result = $res_358;
					$this->setPos($pos_358);
					$_389 = \false; break;
				}
				while(\false);
				if($_389 === \false) { $_391 = \false; break; }
				$_391 = \true; break;
			}
			while(\false);
			if($_391 === \false) {
				$result = $res_392;
				$this->setPos($pos_392);
				unset($res_392, $pos_392);
				break;
			}
		}
		$_393 = \true; break;
	}
	while(\false);
	if($_393 === \true) { return $this->finalise($result); }
	if($_393 === \false) { return \false; }
}

public function Compare_BitShift (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Compare_StrictEqual (&$result, $sub) {
        $this->check_equal($result, $sub['operand']['val'], function($a, $b) {
            return $a === $b;
        });
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

public function Compare_StrictNotEqual (&$result, $sub) {
        $this->check_equal($result, $sub['operand']['val'], function($a, $b) {
            return $a !== $b;
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

/* And: "&&" > operand:Compare > */
protected $match_And_typestack = ['And'];
function match_And($stack = []) {
	$matchrule = 'And';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_399 = \null;
	do {
		if (($subres = $this->literal('&&')) !== \false) { $result["text"] .= $subres; }
		else { $_399 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_399 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_399 = \true; break;
	}
	while(\false);
	if($_399 === \true) { return $this->finalise($result); }
	if($_399 === \false) { return \false; }
}


/* Or: "||" > operand:Compare > */
protected $match_Or_typestack = ['Or'];
function match_Or($stack = []) {
	$matchrule = 'Or';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_405 = \null;
	do {
		if (($subres = $this->literal('||')) !== \false) { $result["text"] .= $subres; }
		else { $_405 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_405 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_405 = \true; break;
	}
	while(\false);
	if($_405 === \true) { return $this->finalise($result); }
	if($_405 === \false) { return \false; }
}


/* Boolean: Compare > (And | Or ) * */
protected $match_Boolean_typestack = ['Boolean'];
function match_Boolean($stack = []) {
	$matchrule = 'Boolean';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_416 = \null;
	do {
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_416 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_415 = $result;
			$pos_415 = $this->pos;
			$_414 = \null;
			do {
				$_412 = \null;
				do {
					$res_409 = $result;
					$pos_409 = $this->pos;
					$key = 'match_'.'And'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_412 = \true; break;
					}
					$result = $res_409;
					$this->setPos($pos_409);
					$key = 'match_'.'Or'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_412 = \true; break;
					}
					$result = $res_409;
					$this->setPos($pos_409);
					$_412 = \false; break;
				}
				while(\false);
				if($_412 === \false) { $_414 = \false; break; }
				$_414 = \true; break;
			}
			while(\false);
			if($_414 === \false) {
				$result = $res_415;
				$this->setPos($pos_415);
				unset($res_415, $pos_415);
				break;
			}
		}
		$_416 = \true; break;
	}
	while(\false);
	if($_416 === \true) { return $this->finalise($result); }
	if($_416 === \false) { return \false; }
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

/* Expr: Boolean */
protected $match_Expr_typestack = ['Expr'];
function match_Expr($stack = []) {
	$matchrule = 'Expr';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$key = 'match_'.'Boolean'; $pos = $this->pos;
	$subres = $this->packhas($key, $pos)
		? $this->packread($key, $pos)
		: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
	if ($subres !== \false) {
		$this->store($result, $subres);
		return $this->finalise($result);
	}
	else { return \false; }
}

public function Expr_Boolean (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

/* Start: (VariableAssignment | FunctionAssignment | Expr ) ";"? */
protected $match_Start_typestack = ['Start'];
function match_Start($stack = []) {
	$matchrule = 'Start';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_431 = \null;
	do {
		$_428 = \null;
		do {
			$_426 = \null;
			do {
				$res_419 = $result;
				$pos_419 = $this->pos;
				$key = 'match_'.'VariableAssignment'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_426 = \true; break;
				}
				$result = $res_419;
				$this->setPos($pos_419);
				$_424 = \null;
				do {
					$res_421 = $result;
					$pos_421 = $this->pos;
					$key = 'match_'.'FunctionAssignment'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_424 = \true; break;
					}
					$result = $res_421;
					$this->setPos($pos_421);
					$key = 'match_'.'Expr'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_424 = \true; break;
					}
					$result = $res_421;
					$this->setPos($pos_421);
					$_424 = \false; break;
				}
				while(\false);
				if($_424 === \true) { $_426 = \true; break; }
				$result = $res_419;
				$this->setPos($pos_419);
				$_426 = \false; break;
			}
			while(\false);
			if($_426 === \false) { $_428 = \false; break; }
			$_428 = \true; break;
		}
		while(\false);
		if($_428 === \false) { $_431 = \false; break; }
		$res_430 = $result;
		$pos_430 = $this->pos;
		if (\substr($this->string, $this->pos, 1) === ';') {
			$this->addPos(1);
			$result["text"] .= ';';
		}
		else {
			$result = $res_430;
			$this->setPos($pos_430);
			unset($res_430, $pos_430);
		}
		$_431 = \true; break;
	}
	while(\false);
	if($_431 === \true) { return $this->finalise($result); }
	if($_431 === \false) { return \false; }
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

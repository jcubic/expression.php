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
        return array_keys($value) == ['type', 'value'];
    }
    private function with_type($value, $type = null) {
        if ($this->is_typed($value)) {
            return $value;
        }
        return ['type' => is_string($type) ? $type : gettype($value), 'value' => $value];
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
    private function validate_array($operation, $object) {
        $this->validate_types(['array'], $operation, $object);
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
        if (!is_string($value)) {
            throw new \Exception("Internal Error: invalid value pass to maybe_regex");
        }
        if (preg_match("/^(\W)[^\\1]+\\1[imsxUXJ]*$/", $value)) {
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
            $tmp_file = tempnam(sys_get_temp_dir(), 'ccf');
            file_put_contents($tmp_file, '<?php $code ');
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
           return $expr->evaluate("' . addslashes($body) . '");
        };';
        $this->functions[$name] = $this->_eval($code);
    }
    private function shift($operation, $left, $right, $fn) {
        $this->validate_number($operation, $left);
        $this->validate_number($operation, $right);
        return $this->with_type($fn($left['value'], $right['value']));
    }

/* Name: (/[A-Za-z_]/ /[A-Za-z_0-9]/* | '$' /[0-9A-Za-z_]+/) */
protected $match_Name_typestack = ['Name'];
function match_Name($stack = []) {
	$matchrule = 'Name';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_11 = \null;
	do {
		$_9 = \null;
		do {
			$res_0 = $result;
			$pos_0 = $this->pos;
			$_3 = \null;
			do {
				if (($subres = $this->rx('/[A-Za-z_]/')) !== \false) { $result["text"] .= $subres; }
				else { $_3 = \false; break; }
				while (\true) {
					$res_2 = $result;
					$pos_2 = $this->pos;
					if (($subres = $this->rx('/[A-Za-z_0-9]/')) !== \false) { $result["text"] .= $subres; }
					else {
						$result = $res_2;
						$this->setPos($pos_2);
						unset($res_2, $pos_2);
						break;
					}
				}
				$_3 = \true; break;
			}
			while(\false);
			if($_3 === \true) { $_9 = \true; break; }
			$result = $res_0;
			$this->setPos($pos_0);
			$_7 = \null;
			do {
				if (\substr($this->string, $this->pos, 1) === '$') {
					$this->addPos(1);
					$result["text"] .= '$';
				}
				else { $_7 = \false; break; }
				if (($subres = $this->rx('/[0-9A-Za-z_]+/')) !== \false) { $result["text"] .= $subres; }
				else { $_7 = \false; break; }
				$_7 = \true; break;
			}
			while(\false);
			if($_7 === \true) { $_9 = \true; break; }
			$result = $res_0;
			$this->setPos($pos_0);
			$_9 = \false; break;
		}
		while(\false);
		if($_9 === \false) { $_11 = \false; break; }
		$_11 = \true; break;
	}
	while(\false);
	if($_11 === \true) { return $this->finalise($result); }
	if($_11 === \false) { return \false; }
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

/* SingleQuoted: /'[^'\\]*(?:\\[\S\s][^'\\]*)*'/ */
protected $match_SingleQuoted_typestack = ['SingleQuoted'];
function match_SingleQuoted($stack = []) {
	$matchrule = 'SingleQuoted';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/\'[^\'\\\\]*(?:\\\\[\S\s][^\'\\\\]*)*\'/')) !== \false) {
		$result["text"] .= $subres;
		return $this->finalise($result);
	}
	else { return \false; }
}


/* DoubleQuoted: /"[^"\\]*(?:\\[\S\s][^"\\]*)*"/ */
protected $match_DoubleQuoted_typestack = ['DoubleQuoted'];
function match_DoubleQuoted($stack = []) {
	$matchrule = 'DoubleQuoted';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/"[^"\\\\]*(?:\\\\[\S\s][^"\\\\]*)*"/')) !== \false) {
		$result["text"] .= $subres;
		return $this->finalise($result);
	}
	else { return \false; }
}


/* String: SingleQuoted | DoubleQuoted */
protected $match_String_typestack = ['String'];
function match_String($stack = []) {
	$matchrule = 'String';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_20 = \null;
	do {
		$res_17 = $result;
		$pos_17 = $this->pos;
		$key = 'match_'.'SingleQuoted'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_20 = \true; break;
		}
		$result = $res_17;
		$this->setPos($pos_17);
		$key = 'match_'.'DoubleQuoted'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_20 = \true; break;
		}
		$result = $res_17;
		$this->setPos($pos_17);
		$_20 = \false; break;
	}
	while(\false);
	if($_20 === \true) { return $this->finalise($result); }
	if($_20 === \false) { return \false; }
}

public function String_SingleQuoted (&$result, $sub) {
         $value = $sub['text'];
         $result['val'] = trim(stripslashes($value), "'");
    }

public function String_DoubleQuoted (&$result, $sub) {
         $value = $sub['text'];
         $result['val'] = trim(stripslashes($value), '"');
    }

/* Hex: '0x' /[0-9A-Fa-f]+/ */
protected $match_Hex_typestack = ['Hex'];
function match_Hex($stack = []) {
	$matchrule = 'Hex';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_24 = \null;
	do {
		if (($subres = $this->literal('0x')) !== \false) { $result["text"] .= $subres; }
		else { $_24 = \false; break; }
		if (($subres = $this->rx('/[0-9A-Fa-f]+/')) !== \false) { $result["text"] .= $subres; }
		else { $_24 = \false; break; }
		$_24 = \true; break;
	}
	while(\false);
	if($_24 === \true) { return $this->finalise($result); }
	if($_24 === \false) { return \false; }
}


/* Binary: '0b' /[01]+/ */
protected $match_Binary_typestack = ['Binary'];
function match_Binary($stack = []) {
	$matchrule = 'Binary';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_28 = \null;
	do {
		if (($subres = $this->literal('0b')) !== \false) { $result["text"] .= $subres; }
		else { $_28 = \false; break; }
		if (($subres = $this->rx('/[01]+/')) !== \false) { $result["text"] .= $subres; }
		else { $_28 = \false; break; }
		$_28 = \true; break;
	}
	while(\false);
	if($_28 === \true) { return $this->finalise($result); }
	if($_28 === \false) { return \false; }
}


/* Decimal: /[0-9]+/ */
protected $match_Decimal_typestack = ['Decimal'];
function match_Decimal($stack = []) {
	$matchrule = 'Decimal';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/[0-9]+/')) !== \false) {
		$result["text"] .= $subres;
		return $this->finalise($result);
	}
	else { return \false; }
}


/* Float: /[0-9.]+e[0-9]+|[0-9]+(?:\.[0-9]*)?|\.[0-9]+/ */
protected $match_Float_typestack = ['Float'];
function match_Float($stack = []) {
	$matchrule = 'Float';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/[0-9.]+e[0-9]+|[0-9]+(?:\.[0-9]*)?|\.[0-9]+/')) !== \false) {
		$result["text"] .= $subres;
		return $this->finalise($result);
	}
	else { return \false; }
}


/* Number: Hex | Binary | Float | Decimal */
protected $match_Number_typestack = ['Number'];
function match_Number($stack = []) {
	$matchrule = 'Number';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_43 = \null;
	do {
		$res_32 = $result;
		$pos_32 = $this->pos;
		$key = 'match_'.'Hex'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_43 = \true; break;
		}
		$result = $res_32;
		$this->setPos($pos_32);
		$_41 = \null;
		do {
			$res_34 = $result;
			$pos_34 = $this->pos;
			$key = 'match_'.'Binary'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres);
				$_41 = \true; break;
			}
			$result = $res_34;
			$this->setPos($pos_34);
			$_39 = \null;
			do {
				$res_36 = $result;
				$pos_36 = $this->pos;
				$key = 'match_'.'Float'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_39 = \true; break;
				}
				$result = $res_36;
				$this->setPos($pos_36);
				$key = 'match_'.'Decimal'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_39 = \true; break;
				}
				$result = $res_36;
				$this->setPos($pos_36);
				$_39 = \false; break;
			}
			while(\false);
			if($_39 === \true) { $_41 = \true; break; }
			$result = $res_34;
			$this->setPos($pos_34);
			$_41 = \false; break;
		}
		while(\false);
		if($_41 === \true) { $_43 = \true; break; }
		$result = $res_32;
		$this->setPos($pos_32);
		$_43 = \false; break;
	}
	while(\false);
	if($_43 === \true) { return $this->finalise($result); }
	if($_43 === \false) { return \false; }
}

public function Number_Hex (&$result, $sub) {
        $value = hexdec($sub['text']);
        $result['val'] = $this->with_type($value);
    }

public function Number_Binary (&$result, $sub) {
        $value = bindec($sub['text']);
        $result['val'] = $this->with_type($value);
    }

public function Number_Decimal (&$result, $sub) {
        $value = intval($sub['text']);
        $result['val'] = $this->with_type($value);
    }

public function Number_Float (&$result, $sub) {
        $value = floatval($sub['text']);
        $result['val'] = $this->with_type($value);
    }

/* Consts: 'true' | 'false' | 'null' */
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


/* SimpleValue: Consts | RegExp | String | Number */
protected $match_SimpleValue_typestack = ['SimpleValue'];
function match_SimpleValue($stack = []) {
	$matchrule = 'SimpleValue';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_69 = \null;
	do {
		$res_58 = $result;
		$pos_58 = $this->pos;
		$key = 'match_'.'Consts'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_69 = \true; break;
		}
		$result = $res_58;
		$this->setPos($pos_58);
		$_67 = \null;
		do {
			$res_60 = $result;
			$pos_60 = $this->pos;
			$key = 'match_'.'RegExp'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres);
				$_67 = \true; break;
			}
			$result = $res_60;
			$this->setPos($pos_60);
			$_65 = \null;
			do {
				$res_62 = $result;
				$pos_62 = $this->pos;
				$key = 'match_'.'String'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_65 = \true; break;
				}
				$result = $res_62;
				$this->setPos($pos_62);
				$key = 'match_'.'Number'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_65 = \true; break;
				}
				$result = $res_62;
				$this->setPos($pos_62);
				$_65 = \false; break;
			}
			while(\false);
			if($_65 === \true) { $_67 = \true; break; }
			$result = $res_60;
			$this->setPos($pos_60);
			$_67 = \false; break;
		}
		while(\false);
		if($_67 === \true) { $_69 = \true; break; }
		$result = $res_58;
		$this->setPos($pos_58);
		$_69 = \false; break;
	}
	while(\false);
	if($_69 === \true) { return $this->finalise($result); }
	if($_69 === \false) { return \false; }
}

public function SimpleValue_Consts (&$result, $sub) {
        $result['val'] = $this->with_type(json_decode($sub['text']));
    }

public function SimpleValue_RegExp (&$result, $sub) {
        $result['val'] = $this->with_type($sub['text'], 'regex');
    }

public function SimpleValue_String (&$result, $sub) {
        $result['val'] = $this->maybe_regex($sub['val']);
    }

public function SimpleValue_Number (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

/* JSON: /([\[{](?>"(?:[^"]|\\")*"|[^[{\]}]|(?1))*[\]}])/ */
protected $match_JSON_typestack = ['JSON'];
function match_JSON($stack = []) {
	$matchrule = 'JSON';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/([\[{](?>"(?:[^"]|\\\\")*"|[^[{\]}]|(?1))*[\]}])/')) !== \false) {
		$result["text"] .= $subres;
		return $this->finalise($result);
	}
	else { return \false; }
}


/* Value: JSON > | SimpleValue > | FunctionCall > | VariableReference > | '(' > Expr > ')' > */
protected $match_Value_typestack = ['Value'];
function match_Value($stack = []) {
	$matchrule = 'Value';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_106 = \null;
	do {
		$res_72 = $result;
		$pos_72 = $this->pos;
		$_75 = \null;
		do {
			$key = 'match_'.'JSON'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else { $_75 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$_75 = \true; break;
		}
		while(\false);
		if($_75 === \true) { $_106 = \true; break; }
		$result = $res_72;
		$this->setPos($pos_72);
		$_104 = \null;
		do {
			$res_77 = $result;
			$pos_77 = $this->pos;
			$_80 = \null;
			do {
				$key = 'match_'.'SimpleValue'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_80 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_80 = \true; break;
			}
			while(\false);
			if($_80 === \true) { $_104 = \true; break; }
			$result = $res_77;
			$this->setPos($pos_77);
			$_102 = \null;
			do {
				$res_82 = $result;
				$pos_82 = $this->pos;
				$_85 = \null;
				do {
					$key = 'match_'.'FunctionCall'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) { $this->store($result, $subres); }
					else { $_85 = \false; break; }
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					$_85 = \true; break;
				}
				while(\false);
				if($_85 === \true) { $_102 = \true; break; }
				$result = $res_82;
				$this->setPos($pos_82);
				$_100 = \null;
				do {
					$res_87 = $result;
					$pos_87 = $this->pos;
					$_90 = \null;
					do {
						$key = 'match_'.'VariableReference'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) { $this->store($result, $subres); }
						else { $_90 = \false; break; }
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						$_90 = \true; break;
					}
					while(\false);
					if($_90 === \true) { $_100 = \true; break; }
					$result = $res_87;
					$this->setPos($pos_87);
					$_98 = \null;
					do {
						if (\substr($this->string, $this->pos, 1) === '(') {
							$this->addPos(1);
							$result["text"] .= '(';
						}
						else { $_98 = \false; break; }
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						$key = 'match_'.'Expr'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) { $this->store($result, $subres); }
						else { $_98 = \false; break; }
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						if (\substr($this->string, $this->pos, 1) === ')') {
							$this->addPos(1);
							$result["text"] .= ')';
						}
						else { $_98 = \false; break; }
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						$_98 = \true; break;
					}
					while(\false);
					if($_98 === \true) { $_100 = \true; break; }
					$result = $res_87;
					$this->setPos($pos_87);
					$_100 = \false; break;
				}
				while(\false);
				if($_100 === \true) { $_102 = \true; break; }
				$result = $res_82;
				$this->setPos($pos_82);
				$_102 = \false; break;
			}
			while(\false);
			if($_102 === \true) { $_104 = \true; break; }
			$result = $res_77;
			$this->setPos($pos_77);
			$_104 = \false; break;
		}
		while(\false);
		if($_104 === \true) { $_106 = \true; break; }
		$result = $res_72;
		$this->setPos($pos_72);
		$_106 = \false; break;
	}
	while(\false);
	if($_106 === \true) { return $this->finalise($result); }
	if($_106 === \false) { return \false; }
}

public function Value_JSON (&$result, $sub) {
        $result['val'] = $this->with_type(json_decode($sub['text'], true));
    }

public function Value_SimpleValue (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Value_FunctionCall (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Value_VariableReference (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Value_Expr (&$result, $sub ) {
        $result['val'] = $sub['val'];
    }

/* Call: Name '(' > ( > Expr > ','? > ) * > ')' > */
protected $match_Call_typestack = ['Call'];
function match_Call($stack = []) {
	$matchrule = 'Call';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_121 = \null;
	do {
		$key = 'match_'.'Name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_121 = \false; break; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_121 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_117 = $result;
			$pos_117 = $this->pos;
			$_116 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'Expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_116 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$res_114 = $result;
				$pos_114 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else {
					$result = $res_114;
					$this->setPos($pos_114);
					unset($res_114, $pos_114);
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_116 = \true; break;
			}
			while(\false);
			if($_116 === \false) {
				$result = $res_117;
				$this->setPos($pos_117);
				unset($res_117, $pos_117);
				break;
			}
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_121 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_121 = \true; break;
	}
	while(\false);
	if($_121 === \true) { return $this->finalise($result); }
	if($_121 === \false) { return \false; }
}

public function Call_Name (&$result, $sub) {
        $name = $sub['text'];
        $result['val'] = [
            'args' => [],
            'name' => $name
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
        if ($is_builtin && $name == 'ln') {
            $name = 'log';
        }
        $function = new ReflectionFunction($is_builtin ? $name : $this->functions[$name]);
        $params_require_count = $function->getNumberOfRequiredParameters();
        $params_all_count = $function->getNumberOfParameters();
        if ($args_count < $params_require_count && $args_count > $params_all_count) {
            throw new Exception("Function '$name' expected $params_count params got $args_count");
        }
        $result['val'] = $this->with_type($function->invokeArgs($args));
    }

/* PowerOp: op:('^' | '**') > operand:Value > */
protected $match_PowerOp_typestack = ['PowerOp'];
function match_PowerOp($stack = []) {
	$matchrule = 'PowerOp';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_134 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "op");
		$_129 = \null;
		do {
			$_127 = \null;
			do {
				$res_124 = $result;
				$pos_124 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === '^') {
					$this->addPos(1);
					$result["text"] .= '^';
					$_127 = \true; break;
				}
				$result = $res_124;
				$this->setPos($pos_124);
				if (($subres = $this->literal('**')) !== \false) {
					$result["text"] .= $subres;
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
		if($_129 === \true) {
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'op');
		}
		if($_129 === \false) {
			$result = \array_pop($stack);
			$_134 = \false; break;
		}
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


/* Power: Value > PowerOp * */
protected $match_Power_typestack = ['Power'];
function match_Power($stack = []) {
	$matchrule = 'Power';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_139 = \null;
	do {
		$key = 'match_'.'Value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_139 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_138 = $result;
			$pos_138 = $this->pos;
			$key = 'match_'.'PowerOp'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else {
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

public function Power_Value (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Power_PowerOp (&$result, $sub) {
        $object = $sub['operand']['val'];
        $op = $sub['op']['text'];
        $this->validate_number($op, $object);
        $this->validate_number($op, $result['val']);
        $result['val'] = $this->with_type(pow($result['val']['value'],  $object['value']));
    }

/* UnaryMinus: '-' > operand:Power > */
protected $match_UnaryMinus_typestack = ['UnaryMinus'];
function match_UnaryMinus($stack = []) {
	$matchrule = 'UnaryMinus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_145 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_145 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Power'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_145 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_145 = \true; break;
	}
	while(\false);
	if($_145 === \true) { return $this->finalise($result); }
	if($_145 === \false) { return \false; }
}


/* UnaryPlus: '+' > operand:Power > */
protected $match_UnaryPlus_typestack = ['UnaryPlus'];
function match_UnaryPlus($stack = []) {
	$matchrule = 'UnaryPlus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_151 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_151 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Power'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_151 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_151 = \true; break;
	}
	while(\false);
	if($_151 === \true) { return $this->finalise($result); }
	if($_151 === \false) { return \false; }
}


/* Negation: '!' > operand:Power > */
protected $match_Negation_typestack = ['Negation'];
function match_Negation($stack = []) {
	$matchrule = 'Negation';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_157 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '!') {
			$this->addPos(1);
			$result["text"] .= '!';
		}
		else { $_157 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Power'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_157 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_157 = \true; break;
	}
	while(\false);
	if($_157 === \true) { return $this->finalise($result); }
	if($_157 === \false) { return \false; }
}


/* Unary: ( Negation | UnaryPlus | UnaryMinus | Power ) */
protected $match_Unary_typestack = ['Unary'];
function match_Unary($stack = []) {
	$matchrule = 'Unary';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_172 = \null;
	do {
		$_170 = \null;
		do {
			$res_159 = $result;
			$pos_159 = $this->pos;
			$key = 'match_'.'Negation'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres);
				$_170 = \true; break;
			}
			$result = $res_159;
			$this->setPos($pos_159);
			$_168 = \null;
			do {
				$res_161 = $result;
				$pos_161 = $this->pos;
				$key = 'match_'.'UnaryPlus'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_168 = \true; break;
				}
				$result = $res_161;
				$this->setPos($pos_161);
				$_166 = \null;
				do {
					$res_163 = $result;
					$pos_163 = $this->pos;
					$key = 'match_'.'UnaryMinus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_166 = \true; break;
					}
					$result = $res_163;
					$this->setPos($pos_163);
					$key = 'match_'.'Power'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_166 = \true; break;
					}
					$result = $res_163;
					$this->setPos($pos_163);
					$_166 = \false; break;
				}
				while(\false);
				if($_166 === \true) { $_168 = \true; break; }
				$result = $res_161;
				$this->setPos($pos_161);
				$_168 = \false; break;
			}
			while(\false);
			if($_168 === \true) { $_170 = \true; break; }
			$result = $res_159;
			$this->setPos($pos_159);
			$_170 = \false; break;
		}
		while(\false);
		if($_170 === \false) { $_172 = \false; break; }
		$_172 = \true; break;
	}
	while(\false);
	if($_172 === \true) { return $this->finalise($result); }
	if($_172 === \false) { return \false; }
}

public function Unary_Power (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Unary_UnaryPlus (&$result, $sub) {
        $val = $sub['operand']['val'];
        if ($this->is_string($val)) {
            $val = floatval($val);
        }
        $result['val'] = $val;
    }

public function Unary_UnaryMinus (&$result, $sub) {
        $object = $sub['operand']['val'];
        $this->validate_number('-', $object);
        $result['val'] = $this->with_type($object['value'] * -1);
    }

public function Unary_Negation (&$result, $sub) {
        $object = $sub['operand']['val'];
        $result['val'] = $this->with_type(!$object['value']);
    }

/* Times: '*' > operand:Unary > */
protected $match_Times_typestack = ['Times'];
function match_Times($stack = []) {
	$matchrule = 'Times';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_178 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '*') {
			$this->addPos(1);
			$result["text"] .= '*';
		}
		else { $_178 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_178 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_178 = \true; break;
	}
	while(\false);
	if($_178 === \true) { return $this->finalise($result); }
	if($_178 === \false) { return \false; }
}


/* Div: '/' > operand:Unary > */
protected $match_Div_typestack = ['Div'];
function match_Div($stack = []) {
	$matchrule = 'Div';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_184 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '/') {
			$this->addPos(1);
			$result["text"] .= '/';
		}
		else { $_184 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_184 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_184 = \true; break;
	}
	while(\false);
	if($_184 === \true) { return $this->finalise($result); }
	if($_184 === \false) { return \false; }
}


/* Mod: '%' > operand:Unary > */
protected $match_Mod_typestack = ['Mod'];
function match_Mod($stack = []) {
	$matchrule = 'Mod';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_190 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '%') {
			$this->addPos(1);
			$result["text"] .= '%';
		}
		else { $_190 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_190 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_190 = \true; break;
	}
	while(\false);
	if($_190 === \true) { return $this->finalise($result); }
	if($_190 === \false) { return \false; }
}


/* ImplicitTimes: operand:Power > */
protected $match_ImplicitTimes_typestack = ['ImplicitTimes'];
function match_ImplicitTimes($stack = []) {
	$matchrule = 'ImplicitTimes';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_194 = \null;
	do {
		$key = 'match_'.'Power'; $pos = $this->pos;
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


/* Property: '[' > operand:Expr > ']' > */
protected $match_Property_typestack = ['Property'];
function match_Property($stack = []) {
	$matchrule = 'Property';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_202 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '[') {
			$this->addPos(1);
			$result["text"] .= '[';
		}
		else { $_202 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_202 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ']') {
			$this->addPos(1);
			$result["text"] .= ']';
		}
		else { $_202 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_202 = \true; break;
	}
	while(\false);
	if($_202 === \true) { return $this->finalise($result); }
	if($_202 === \false) { return \false; }
}


/* Product: Unary > ( Times | Div | Mod | Property | ImplicitTimes ) * */
protected $match_Product_typestack = ['Product'];
function match_Product($stack = []) {
	$matchrule = 'Product';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_225 = \null;
	do {
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_225 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_224 = $result;
			$pos_224 = $this->pos;
			$_223 = \null;
			do {
				$_221 = \null;
				do {
					$res_206 = $result;
					$pos_206 = $this->pos;
					$key = 'match_'.'Times'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_221 = \true; break;
					}
					$result = $res_206;
					$this->setPos($pos_206);
					$_219 = \null;
					do {
						$res_208 = $result;
						$pos_208 = $this->pos;
						$key = 'match_'.'Div'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_219 = \true; break;
						}
						$result = $res_208;
						$this->setPos($pos_208);
						$_217 = \null;
						do {
							$res_210 = $result;
							$pos_210 = $this->pos;
							$key = 'match_'.'Mod'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres);
								$_217 = \true; break;
							}
							$result = $res_210;
							$this->setPos($pos_210);
							$_215 = \null;
							do {
								$res_212 = $result;
								$pos_212 = $this->pos;
								$key = 'match_'.'Property'; $pos = $this->pos;
								$subres = $this->packhas($key, $pos)
									? $this->packread($key, $pos)
									: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
								if ($subres !== \false) {
									$this->store($result, $subres);
									$_215 = \true; break;
								}
								$result = $res_212;
								$this->setPos($pos_212);
								$key = 'match_'.'ImplicitTimes'; $pos = $this->pos;
								$subres = $this->packhas($key, $pos)
									? $this->packread($key, $pos)
									: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
								if ($subres !== \false) {
									$this->store($result, $subres);
									$_215 = \true; break;
								}
								$result = $res_212;
								$this->setPos($pos_212);
								$_215 = \false; break;
							}
							while(\false);
							if($_215 === \true) { $_217 = \true; break; }
							$result = $res_210;
							$this->setPos($pos_210);
							$_217 = \false; break;
						}
						while(\false);
						if($_217 === \true) { $_219 = \true; break; }
						$result = $res_208;
						$this->setPos($pos_208);
						$_219 = \false; break;
					}
					while(\false);
					if($_219 === \true) { $_221 = \true; break; }
					$result = $res_206;
					$this->setPos($pos_206);
					$_221 = \false; break;
				}
				while(\false);
				if($_221 === \false) { $_223 = \false; break; }
				$_223 = \true; break;
			}
			while(\false);
			if($_223 === \false) {
				$result = $res_224;
				$this->setPos($pos_224);
				unset($res_224, $pos_224);
				break;
			}
		}
		$_225 = \true; break;
	}
	while(\false);
	if($_225 === \true) { return $this->finalise($result); }
	if($_225 === \false) { return \false; }
}

public function Product_Unary (&$result, $sub) {
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
        $object = $sub['operand']['val'];
        $this->validate_number('[*]', $object);
        $this->validate_number('[*]', $result['val']);
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

public function Product_Expr (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Product_Property (&$result, $sub) {
        $prop = $sub['operand']['val'];
        $object = $result['val'];
        $this->validate_array('[', $object);
        $this->validate_types(['string', 'double', 'integer', 'boolean'], '[', $prop);
        $result['val'] = $this->with_type($object['value'][$prop['value']]);
    }

/* Plus: '+' > operand:Product > */
protected $match_Plus_typestack = ['Plus'];
function match_Plus($stack = []) {
	$matchrule = 'Plus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_231 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
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


/* Minus: '-' > operand:Product > */
protected $match_Minus_typestack = ['Minus'];
function match_Minus($stack = []) {
	$matchrule = 'Minus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_237 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_237 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_237 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_237 = \true; break;
	}
	while(\false);
	if($_237 === \true) { return $this->finalise($result); }
	if($_237 === \false) { return \false; }
}


/* Sum: Product > ( Plus | Minus ) * */
protected $match_Sum_typestack = ['Sum'];
function match_Sum($stack = []) {
	$matchrule = 'Sum';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_248 = \null;
	do {
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_248 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_247 = $result;
			$pos_247 = $this->pos;
			$_246 = \null;
			do {
				$_244 = \null;
				do {
					$res_241 = $result;
					$pos_241 = $this->pos;
					$key = 'match_'.'Plus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_244 = \true; break;
					}
					$result = $res_241;
					$this->setPos($pos_241);
					$key = 'match_'.'Minus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_244 = \true; break;
					}
					$result = $res_241;
					$this->setPos($pos_241);
					$_244 = \false; break;
				}
				while(\false);
				if($_244 === \false) { $_246 = \false; break; }
				$_246 = \true; break;
			}
			while(\false);
			if($_246 === \false) {
				$result = $res_247;
				$this->setPos($pos_247);
				unset($res_247, $pos_247);
				break;
			}
		}
		$_248 = \true; break;
	}
	while(\false);
	if($_248 === \true) { return $this->finalise($result); }
	if($_248 === \false) { return \false; }
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

/* VariableAssignment: Variable > '=' > Expr */
protected $match_VariableAssignment_typestack = ['VariableAssignment'];
function match_VariableAssignment($stack = []) {
	$matchrule = 'VariableAssignment';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_255 = \null;
	do {
		$key = 'match_'.'Variable'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_255 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '=') {
			$this->addPos(1);
			$result["text"] .= '=';
		}
		else { $_255 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_255 = \false; break; }
		$_255 = \true; break;
	}
	while(\false);
	if($_255 === \true) { return $this->finalise($result); }
	if($_255 === \false) { return \false; }
}

public function VariableAssignment_Variable (&$result, $sub) {
        $result['val'] = ['name' => $sub['val']];
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


/* FunctionAssignment: Name '(' > ( > Variable > ','? > ) * ')' > '=' !/[=~]/ > FunctionBody */
protected $match_FunctionAssignment_typestack = ['FunctionAssignment'];
function match_FunctionAssignment($stack = []) {
	$matchrule = 'FunctionAssignment';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_274 = \null;
	do {
		$key = 'match_'.'Name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_274 = \false; break; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_274 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_267 = $result;
			$pos_267 = $this->pos;
			$_266 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'Variable'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_266 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$res_264 = $result;
				$pos_264 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else {
					$result = $res_264;
					$this->setPos($pos_264);
					unset($res_264, $pos_264);
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_266 = \true; break;
			}
			while(\false);
			if($_266 === \false) {
				$result = $res_267;
				$this->setPos($pos_267);
				unset($res_267, $pos_267);
				break;
			}
		}
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_274 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '=') {
			$this->addPos(1);
			$result["text"] .= '=';
		}
		else { $_274 = \false; break; }
		$res_271 = $result;
		$pos_271 = $this->pos;
		if (($subres = $this->rx('/[=~]/')) !== \false) {
			$result["text"] .= $subres;
			$result = $res_271;
			$this->setPos($pos_271);
			$_274 = \false; break;
		}
		else {
			$result = $res_271;
			$this->setPos($pos_271);
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'FunctionBody'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_274 = \false; break; }
		$_274 = \true; break;
	}
	while(\false);
	if($_274 === \true) { return $this->finalise($result); }
	if($_274 === \false) { return \false; }
}

public function FunctionAssignment_Name (&$result, $sub) {
        $name = $sub['text'];
        $result['val'] = [
            'params' => [],
            'name' => $name,
            'body' => null
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
	$_280 = \null;
	do {
		if (($subres = $this->literal('<<')) !== \false) { $result["text"] .= $subres; }
		else { $_280 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Sum'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_280 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_280 = \true; break;
	}
	while(\false);
	if($_280 === \true) { return $this->finalise($result); }
	if($_280 === \false) { return \false; }
}


/* ShiftRight: '>>' > operand:Sum > */
protected $match_ShiftRight_typestack = ['ShiftRight'];
function match_ShiftRight($stack = []) {
	$matchrule = 'ShiftRight';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_286 = \null;
	do {
		if (($subres = $this->literal('>>')) !== \false) { $result["text"] .= $subres; }
		else { $_286 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Sum'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_286 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_286 = \true; break;
	}
	while(\false);
	if($_286 === \true) { return $this->finalise($result); }
	if($_286 === \false) { return \false; }
}


/* BitShift: Sum > (ShiftRight | ShiftLeft) * */
protected $match_BitShift_typestack = ['BitShift'];
function match_BitShift($stack = []) {
	$matchrule = 'BitShift';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_297 = \null;
	do {
		$key = 'match_'.'Sum'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_297 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_296 = $result;
			$pos_296 = $this->pos;
			$_295 = \null;
			do {
				$_293 = \null;
				do {
					$res_290 = $result;
					$pos_290 = $this->pos;
					$key = 'match_'.'ShiftRight'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_293 = \true; break;
					}
					$result = $res_290;
					$this->setPos($pos_290);
					$key = 'match_'.'ShiftLeft'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_293 = \true; break;
					}
					$result = $res_290;
					$this->setPos($pos_290);
					$_293 = \false; break;
				}
				while(\false);
				if($_293 === \false) { $_295 = \false; break; }
				$_295 = \true; break;
			}
			while(\false);
			if($_295 === \false) {
				$result = $res_296;
				$this->setPos($pos_296);
				unset($res_296, $pos_296);
				break;
			}
		}
		$_297 = \true; break;
	}
	while(\false);
	if($_297 === \true) { return $this->finalise($result); }
	if($_297 === \false) { return \false; }
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
	$_303 = \null;
	do {
		if (($subres = $this->literal('===')) !== \false) { $result["text"] .= $subres; }
		else { $_303 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_303 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_303 = \true; break;
	}
	while(\false);
	if($_303 === \true) { return $this->finalise($result); }
	if($_303 === \false) { return \false; }
}


/* StrictNotEqual: '!==' > operand:BitShift > */
protected $match_StrictNotEqual_typestack = ['StrictNotEqual'];
function match_StrictNotEqual($stack = []) {
	$matchrule = 'StrictNotEqual';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_309 = \null;
	do {
		if (($subres = $this->literal('!==')) !== \false) { $result["text"] .= $subres; }
		else { $_309 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_309 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_309 = \true; break;
	}
	while(\false);
	if($_309 === \true) { return $this->finalise($result); }
	if($_309 === \false) { return \false; }
}


/* Equal: '==' > operand:BitShift > */
protected $match_Equal_typestack = ['Equal'];
function match_Equal($stack = []) {
	$matchrule = 'Equal';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_315 = \null;
	do {
		if (($subres = $this->literal('==')) !== \false) { $result["text"] .= $subres; }
		else { $_315 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_315 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_315 = \true; break;
	}
	while(\false);
	if($_315 === \true) { return $this->finalise($result); }
	if($_315 === \false) { return \false; }
}


/* Match: '=~' > operand:BitShift > */
protected $match_Match_typestack = ['Match'];
function match_Match($stack = []) {
	$matchrule = 'Match';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_321 = \null;
	do {
		if (($subres = $this->literal('=~')) !== \false) { $result["text"] .= $subres; }
		else { $_321 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_321 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_321 = \true; break;
	}
	while(\false);
	if($_321 === \true) { return $this->finalise($result); }
	if($_321 === \false) { return \false; }
}


/* NotEqual: '!=' > operand:BitShift > */
protected $match_NotEqual_typestack = ['NotEqual'];
function match_NotEqual($stack = []) {
	$matchrule = 'NotEqual';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_327 = \null;
	do {
		if (($subres = $this->literal('!=')) !== \false) { $result["text"] .= $subres; }
		else { $_327 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_327 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_327 = \true; break;
	}
	while(\false);
	if($_327 === \true) { return $this->finalise($result); }
	if($_327 === \false) { return \false; }
}


/* GreaterEqualThan: '>=' > operand:BitShift > */
protected $match_GreaterEqualThan_typestack = ['GreaterEqualThan'];
function match_GreaterEqualThan($stack = []) {
	$matchrule = 'GreaterEqualThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_333 = \null;
	do {
		if (($subres = $this->literal('>=')) !== \false) { $result["text"] .= $subres; }
		else { $_333 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_333 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_333 = \true; break;
	}
	while(\false);
	if($_333 === \true) { return $this->finalise($result); }
	if($_333 === \false) { return \false; }
}


/* LessEqualThan: '<=' > operand:BitShift > */
protected $match_LessEqualThan_typestack = ['LessEqualThan'];
function match_LessEqualThan($stack = []) {
	$matchrule = 'LessEqualThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_339 = \null;
	do {
		if (($subres = $this->literal('<=')) !== \false) { $result["text"] .= $subres; }
		else { $_339 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_339 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_339 = \true; break;
	}
	while(\false);
	if($_339 === \true) { return $this->finalise($result); }
	if($_339 === \false) { return \false; }
}


/* GreaterThan: '>' > operand:BitShift > */
protected $match_GreaterThan_typestack = ['GreaterThan'];
function match_GreaterThan($stack = []) {
	$matchrule = 'GreaterThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_345 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '>') {
			$this->addPos(1);
			$result["text"] .= '>';
		}
		else { $_345 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_345 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_345 = \true; break;
	}
	while(\false);
	if($_345 === \true) { return $this->finalise($result); }
	if($_345 === \false) { return \false; }
}


/* LessThan: '<' > operand:BitShift > */
protected $match_LessThan_typestack = ['LessThan'];
function match_LessThan($stack = []) {
	$matchrule = 'LessThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_351 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '<') {
			$this->addPos(1);
			$result["text"] .= '<';
		}
		else { $_351 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_351 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_351 = \true; break;
	}
	while(\false);
	if($_351 === \true) { return $this->finalise($result); }
	if($_351 === \false) { return \false; }
}


/* Compare: BitShift > (StrictEqual | Equal | Match | StrictNotEqual | NotEqual | GreaterEqualThan | GreaterThan | LessEqualThan | LessThan ) * */
protected $match_Compare_typestack = ['Compare'];
function match_Compare($stack = []) {
	$matchrule = 'Compare';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_390 = \null;
	do {
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_390 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_389 = $result;
			$pos_389 = $this->pos;
			$_388 = \null;
			do {
				$_386 = \null;
				do {
					$res_355 = $result;
					$pos_355 = $this->pos;
					$key = 'match_'.'StrictEqual'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_386 = \true; break;
					}
					$result = $res_355;
					$this->setPos($pos_355);
					$_384 = \null;
					do {
						$res_357 = $result;
						$pos_357 = $this->pos;
						$key = 'match_'.'Equal'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_384 = \true; break;
						}
						$result = $res_357;
						$this->setPos($pos_357);
						$_382 = \null;
						do {
							$res_359 = $result;
							$pos_359 = $this->pos;
							$key = 'match_'.'Match'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres);
								$_382 = \true; break;
							}
							$result = $res_359;
							$this->setPos($pos_359);
							$_380 = \null;
							do {
								$res_361 = $result;
								$pos_361 = $this->pos;
								$key = 'match_'.'StrictNotEqual'; $pos = $this->pos;
								$subres = $this->packhas($key, $pos)
									? $this->packread($key, $pos)
									: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
								if ($subres !== \false) {
									$this->store($result, $subres);
									$_380 = \true; break;
								}
								$result = $res_361;
								$this->setPos($pos_361);
								$_378 = \null;
								do {
									$res_363 = $result;
									$pos_363 = $this->pos;
									$key = 'match_'.'NotEqual'; $pos = $this->pos;
									$subres = $this->packhas($key, $pos)
										? $this->packread($key, $pos)
										: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
									if ($subres !== \false) {
										$this->store($result, $subres);
										$_378 = \true; break;
									}
									$result = $res_363;
									$this->setPos($pos_363);
									$_376 = \null;
									do {
										$res_365 = $result;
										$pos_365 = $this->pos;
										$key = 'match_'.'GreaterEqualThan'; $pos = $this->pos;
										$subres = $this->packhas($key, $pos)
											? $this->packread($key, $pos)
											: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
										if ($subres !== \false) {
											$this->store($result, $subres);
											$_376 = \true; break;
										}
										$result = $res_365;
										$this->setPos($pos_365);
										$_374 = \null;
										do {
											$res_367 = $result;
											$pos_367 = $this->pos;
											$key = 'match_'.'GreaterThan'; $pos = $this->pos;
											$subres = $this->packhas($key, $pos)
												? $this->packread($key, $pos)
												: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
											if ($subres !== \false) {
												$this->store($result, $subres);
												$_374 = \true; break;
											}
											$result = $res_367;
											$this->setPos($pos_367);
											$_372 = \null;
											do {
												$res_369 = $result;
												$pos_369 = $this->pos;
												$key = 'match_'.'LessEqualThan'; $pos = $this->pos;
												$subres = $this->packhas($key, $pos)
													? $this->packread($key, $pos)
													: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
												if ($subres !== \false) {
													$this->store($result, $subres);
													$_372 = \true; break;
												}
												$result = $res_369;
												$this->setPos($pos_369);
												$key = 'match_'.'LessThan'; $pos = $this->pos;
												$subres = $this->packhas($key, $pos)
													? $this->packread($key, $pos)
													: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
												if ($subres !== \false) {
													$this->store($result, $subres);
													$_372 = \true; break;
												}
												$result = $res_369;
												$this->setPos($pos_369);
												$_372 = \false; break;
											}
											while(\false);
											if($_372 === \true) { $_374 = \true; break; }
											$result = $res_367;
											$this->setPos($pos_367);
											$_374 = \false; break;
										}
										while(\false);
										if($_374 === \true) { $_376 = \true; break; }
										$result = $res_365;
										$this->setPos($pos_365);
										$_376 = \false; break;
									}
									while(\false);
									if($_376 === \true) { $_378 = \true; break; }
									$result = $res_363;
									$this->setPos($pos_363);
									$_378 = \false; break;
								}
								while(\false);
								if($_378 === \true) { $_380 = \true; break; }
								$result = $res_361;
								$this->setPos($pos_361);
								$_380 = \false; break;
							}
							while(\false);
							if($_380 === \true) { $_382 = \true; break; }
							$result = $res_359;
							$this->setPos($pos_359);
							$_382 = \false; break;
						}
						while(\false);
						if($_382 === \true) { $_384 = \true; break; }
						$result = $res_357;
						$this->setPos($pos_357);
						$_384 = \false; break;
					}
					while(\false);
					if($_384 === \true) { $_386 = \true; break; }
					$result = $res_355;
					$this->setPos($pos_355);
					$_386 = \false; break;
				}
				while(\false);
				if($_386 === \false) { $_388 = \false; break; }
				$_388 = \true; break;
			}
			while(\false);
			if($_388 === \false) {
				$result = $res_389;
				$this->setPos($pos_389);
				unset($res_389, $pos_389);
				break;
			}
		}
		$_390 = \true; break;
	}
	while(\false);
	if($_390 === \true) { return $this->finalise($result); }
	if($_390 === \false) { return \false; }
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
            $re = $re['value'];
            throw new Exception("Invalid regular expression: $re");
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

/* And: '&&' > operand:Compare > */
protected $match_And_typestack = ['And'];
function match_And($stack = []) {
	$matchrule = 'And';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_396 = \null;
	do {
		if (($subres = $this->literal('&&')) !== \false) { $result["text"] .= $subres; }
		else { $_396 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_396 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_396 = \true; break;
	}
	while(\false);
	if($_396 === \true) { return $this->finalise($result); }
	if($_396 === \false) { return \false; }
}


/* Or: '||' > operand:Compare > */
protected $match_Or_typestack = ['Or'];
function match_Or($stack = []) {
	$matchrule = 'Or';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_402 = \null;
	do {
		if (($subres = $this->literal('||')) !== \false) { $result["text"] .= $subres; }
		else { $_402 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_402 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_402 = \true; break;
	}
	while(\false);
	if($_402 === \true) { return $this->finalise($result); }
	if($_402 === \false) { return \false; }
}


/* Boolean: Compare > (And | Or ) * */
protected $match_Boolean_typestack = ['Boolean'];
function match_Boolean($stack = []) {
	$matchrule = 'Boolean';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_413 = \null;
	do {
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_413 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_412 = $result;
			$pos_412 = $this->pos;
			$_411 = \null;
			do {
				$_409 = \null;
				do {
					$res_406 = $result;
					$pos_406 = $this->pos;
					$key = 'match_'.'And'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_409 = \true; break;
					}
					$result = $res_406;
					$this->setPos($pos_406);
					$key = 'match_'.'Or'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_409 = \true; break;
					}
					$result = $res_406;
					$this->setPos($pos_406);
					$_409 = \false; break;
				}
				while(\false);
				if($_409 === \false) { $_411 = \false; break; }
				$_411 = \true; break;
			}
			while(\false);
			if($_411 === \false) {
				$result = $res_412;
				$this->setPos($pos_412);
				unset($res_412, $pos_412);
				break;
			}
		}
		$_413 = \true; break;
	}
	while(\false);
	if($_413 === \true) { return $this->finalise($result); }
	if($_413 === \false) { return \false; }
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

/* Start: (VariableAssignment | FunctionAssignment | Expr ) ';'? */
protected $match_Start_typestack = ['Start'];
function match_Start($stack = []) {
	$matchrule = 'Start';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_428 = \null;
	do {
		$_425 = \null;
		do {
			$_423 = \null;
			do {
				$res_416 = $result;
				$pos_416 = $this->pos;
				$key = 'match_'.'VariableAssignment'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_423 = \true; break;
				}
				$result = $res_416;
				$this->setPos($pos_416);
				$_421 = \null;
				do {
					$res_418 = $result;
					$pos_418 = $this->pos;
					$key = 'match_'.'FunctionAssignment'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_421 = \true; break;
					}
					$result = $res_418;
					$this->setPos($pos_418);
					$key = 'match_'.'Expr'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_421 = \true; break;
					}
					$result = $res_418;
					$this->setPos($pos_418);
					$_421 = \false; break;
				}
				while(\false);
				if($_421 === \true) { $_423 = \true; break; }
				$result = $res_416;
				$this->setPos($pos_416);
				$_423 = \false; break;
			}
			while(\false);
			if($_423 === \false) { $_425 = \false; break; }
			$_425 = \true; break;
		}
		while(\false);
		if($_425 === \false) { $_428 = \false; break; }
		$res_427 = $result;
		$pos_427 = $this->pos;
		if (\substr($this->string, $this->pos, 1) === ';') {
			$this->addPos(1);
			$result["text"] .= ';';
		}
		else {
			$result = $res_427;
			$this->setPos($pos_427);
			unset($res_427, $pos_427);
		}
		$_428 = \true; break;
	}
	while(\false);
	if($_428 === \true) { return $this->finalise($result); }
	if($_428 === \false) { return \false; }
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

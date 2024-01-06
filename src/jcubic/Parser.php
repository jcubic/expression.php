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
           return $expr->evaluate(' . json_encode($body) . ');
        };';
        $this->functions[$name] = $this->_eval($code);
    }
    private function shift($operation, $left, $right, $fn) {
        $this->validate_number($operation, $left);
        $this->validate_number($operation, $right);
        return $this->with_type($fn($left['value'], $right['value']));
    }

/* Name: (/[A-Za-z]+/ | '$' /[0-9A-Za-z]+/) */
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

/* Hex: '0x' /[0-9A-Fa-f]+/ */
protected $match_Hex_typestack = ['Hex'];
function match_Hex($stack = []) {
	$matchrule = 'Hex';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_47 = \null;
	do {
		if (($subres = $this->literal('0x')) !== \false) { $result["text"] .= $subres; }
		else { $_47 = \false; break; }
		if (($subres = $this->rx('/[0-9A-Fa-f]+/')) !== \false) { $result["text"] .= $subres; }
		else { $_47 = \false; break; }
		$_47 = \true; break;
	}
	while(\false);
	if($_47 === \true) { return $this->finalise($result); }
	if($_47 === \false) { return \false; }
}


/* Binary: '0b' /[01]+/ */
protected $match_Binary_typestack = ['Binary'];
function match_Binary($stack = []) {
	$matchrule = 'Binary';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_51 = \null;
	do {
		if (($subres = $this->literal('0b')) !== \false) { $result["text"] .= $subres; }
		else { $_51 = \false; break; }
		if (($subres = $this->rx('/[01]+/')) !== \false) { $result["text"] .= $subres; }
		else { $_51 = \false; break; }
		$_51 = \true; break;
	}
	while(\false);
	if($_51 === \true) { return $this->finalise($result); }
	if($_51 === \false) { return \false; }
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
	$_66 = \null;
	do {
		$res_55 = $result;
		$pos_55 = $this->pos;
		$key = 'match_'.'Hex'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_66 = \true; break;
		}
		$result = $res_55;
		$this->setPos($pos_55);
		$_64 = \null;
		do {
			$res_57 = $result;
			$pos_57 = $this->pos;
			$key = 'match_'.'Binary'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres);
				$_64 = \true; break;
			}
			$result = $res_57;
			$this->setPos($pos_57);
			$_62 = \null;
			do {
				$res_59 = $result;
				$pos_59 = $this->pos;
				$key = 'match_'.'Float'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_62 = \true; break;
				}
				$result = $res_59;
				$this->setPos($pos_59);
				$key = 'match_'.'Decimal'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_62 = \true; break;
				}
				$result = $res_59;
				$this->setPos($pos_59);
				$_62 = \false; break;
			}
			while(\false);
			if($_62 === \true) { $_64 = \true; break; }
			$result = $res_57;
			$this->setPos($pos_57);
			$_64 = \false; break;
		}
		while(\false);
		if($_64 === \true) { $_66 = \true; break; }
		$result = $res_55;
		$this->setPos($pos_55);
		$_66 = \false; break;
	}
	while(\false);
	if($_66 === \true) { return $this->finalise($result); }
	if($_66 === \false) { return \false; }
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
	$_75 = \null;
	do {
		$res_68 = $result;
		$pos_68 = $this->pos;
		if (($subres = $this->literal('true')) !== \false) {
			$result["text"] .= $subres;
			$_75 = \true; break;
		}
		$result = $res_68;
		$this->setPos($pos_68);
		$_73 = \null;
		do {
			$res_70 = $result;
			$pos_70 = $this->pos;
			if (($subres = $this->literal('false')) !== \false) {
				$result["text"] .= $subres;
				$_73 = \true; break;
			}
			$result = $res_70;
			$this->setPos($pos_70);
			if (($subres = $this->literal('null')) !== \false) {
				$result["text"] .= $subres;
				$_73 = \true; break;
			}
			$result = $res_70;
			$this->setPos($pos_70);
			$_73 = \false; break;
		}
		while(\false);
		if($_73 === \true) { $_75 = \true; break; }
		$result = $res_68;
		$this->setPos($pos_68);
		$_75 = \false; break;
	}
	while(\false);
	if($_75 === \true) { return $this->finalise($result); }
	if($_75 === \false) { return \false; }
}


/* RegExp: /(?<!\\\\)\/(?:[^\/]|\\\\\/)+\// /[imsxUXJ]/* */
protected $match_RegExp_typestack = ['RegExp'];
function match_RegExp($stack = []) {
	$matchrule = 'RegExp';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_79 = \null;
	do {
		if (($subres = $this->rx('/(?<!\\\\\\\\)\/(?:[^\/]|\\\\\\\\\/)+\//')) !== \false) { $result["text"] .= $subres; }
		else { $_79 = \false; break; }
		while (\true) {
			$res_78 = $result;
			$pos_78 = $this->pos;
			if (($subres = $this->rx('/[imsxUXJ]/')) !== \false) { $result["text"] .= $subres; }
			else {
				$result = $res_78;
				$this->setPos($pos_78);
				unset($res_78, $pos_78);
				break;
			}
		}
		$_79 = \true; break;
	}
	while(\false);
	if($_79 === \true) { return $this->finalise($result); }
	if($_79 === \false) { return \false; }
}


/* SimpleValue: Consts | RegExp | String | Number */
protected $match_SimpleValue_typestack = ['SimpleValue'];
function match_SimpleValue($stack = []) {
	$matchrule = 'SimpleValue';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_92 = \null;
	do {
		$res_81 = $result;
		$pos_81 = $this->pos;
		$key = 'match_'.'Consts'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_92 = \true; break;
		}
		$result = $res_81;
		$this->setPos($pos_81);
		$_90 = \null;
		do {
			$res_83 = $result;
			$pos_83 = $this->pos;
			$key = 'match_'.'RegExp'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres);
				$_90 = \true; break;
			}
			$result = $res_83;
			$this->setPos($pos_83);
			$_88 = \null;
			do {
				$res_85 = $result;
				$pos_85 = $this->pos;
				$key = 'match_'.'String'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_88 = \true; break;
				}
				$result = $res_85;
				$this->setPos($pos_85);
				$key = 'match_'.'Number'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_88 = \true; break;
				}
				$result = $res_85;
				$this->setPos($pos_85);
				$_88 = \false; break;
			}
			while(\false);
			if($_88 === \true) { $_90 = \true; break; }
			$result = $res_83;
			$this->setPos($pos_83);
			$_90 = \false; break;
		}
		while(\false);
		if($_90 === \true) { $_92 = \true; break; }
		$result = $res_81;
		$this->setPos($pos_81);
		$_92 = \false; break;
	}
	while(\false);
	if($_92 === \true) { return $this->finalise($result); }
	if($_92 === \false) { return \false; }
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

/* JSON: /[\[{](?>"(?:[^"]|\\\\")*"|[^[{\]}]|(\1))*[\]}]/ */
protected $match_JSON_typestack = ['JSON'];
function match_JSON($stack = []) {
	$matchrule = 'JSON';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/[\[{](?>"(?:[^"]|\\\\\\\\")*"|[^[{\]}]|(\1))*[\]}]/')) !== \false) {
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
	$_129 = \null;
	do {
		$res_95 = $result;
		$pos_95 = $this->pos;
		$_98 = \null;
		do {
			$key = 'match_'.'JSON'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else { $_98 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$_98 = \true; break;
		}
		while(\false);
		if($_98 === \true) { $_129 = \true; break; }
		$result = $res_95;
		$this->setPos($pos_95);
		$_127 = \null;
		do {
			$res_100 = $result;
			$pos_100 = $this->pos;
			$_103 = \null;
			do {
				$key = 'match_'.'SimpleValue'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_103 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_103 = \true; break;
			}
			while(\false);
			if($_103 === \true) { $_127 = \true; break; }
			$result = $res_100;
			$this->setPos($pos_100);
			$_125 = \null;
			do {
				$res_105 = $result;
				$pos_105 = $this->pos;
				$_108 = \null;
				do {
					$key = 'match_'.'FunctionCall'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) { $this->store($result, $subres); }
					else { $_108 = \false; break; }
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					$_108 = \true; break;
				}
				while(\false);
				if($_108 === \true) { $_125 = \true; break; }
				$result = $res_105;
				$this->setPos($pos_105);
				$_123 = \null;
				do {
					$res_110 = $result;
					$pos_110 = $this->pos;
					$_113 = \null;
					do {
						$key = 'match_'.'VariableReference'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) { $this->store($result, $subres); }
						else { $_113 = \false; break; }
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						$_113 = \true; break;
					}
					while(\false);
					if($_113 === \true) { $_123 = \true; break; }
					$result = $res_110;
					$this->setPos($pos_110);
					$_121 = \null;
					do {
						if (\substr($this->string, $this->pos, 1) === '(') {
							$this->addPos(1);
							$result["text"] .= '(';
						}
						else { $_121 = \false; break; }
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						$key = 'match_'.'Expr'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) { $this->store($result, $subres); }
						else { $_121 = \false; break; }
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
					if($_121 === \true) { $_123 = \true; break; }
					$result = $res_110;
					$this->setPos($pos_110);
					$_123 = \false; break;
				}
				while(\false);
				if($_123 === \true) { $_125 = \true; break; }
				$result = $res_105;
				$this->setPos($pos_105);
				$_125 = \false; break;
			}
			while(\false);
			if($_125 === \true) { $_127 = \true; break; }
			$result = $res_100;
			$this->setPos($pos_100);
			$_127 = \false; break;
		}
		while(\false);
		if($_127 === \true) { $_129 = \true; break; }
		$result = $res_95;
		$this->setPos($pos_95);
		$_129 = \false; break;
	}
	while(\false);
	if($_129 === \true) { return $this->finalise($result); }
	if($_129 === \false) { return \false; }
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
	$_144 = \null;
	do {
		$key = 'match_'.'Name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_144 = \false; break; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_144 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_140 = $result;
			$pos_140 = $this->pos;
			$_139 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'Expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_139 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$res_137 = $result;
				$pos_137 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else {
					$result = $res_137;
					$this->setPos($pos_137);
					unset($res_137, $pos_137);
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_139 = \true; break;
			}
			while(\false);
			if($_139 === \false) {
				$result = $res_140;
				$this->setPos($pos_140);
				unset($res_140, $pos_140);
				break;
			}
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_144 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_144 = \true; break;
	}
	while(\false);
	if($_144 === \true) { return $this->finalise($result); }
	if($_144 === \false) { return \false; }
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
	$_157 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "op");
		$_152 = \null;
		do {
			$_150 = \null;
			do {
				$res_147 = $result;
				$pos_147 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === '^') {
					$this->addPos(1);
					$result["text"] .= '^';
					$_150 = \true; break;
				}
				$result = $res_147;
				$this->setPos($pos_147);
				if (($subres = $this->literal('**')) !== \false) {
					$result["text"] .= $subres;
					$_150 = \true; break;
				}
				$result = $res_147;
				$this->setPos($pos_147);
				$_150 = \false; break;
			}
			while(\false);
			if($_150 === \false) { $_152 = \false; break; }
			$_152 = \true; break;
		}
		while(\false);
		if($_152 === \true) {
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'op');
		}
		if($_152 === \false) {
			$result = \array_pop($stack);
			$_157 = \false; break;
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Value'; $pos = $this->pos;
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


/* Power: Value > PowerOp * */
protected $match_Power_typestack = ['Power'];
function match_Power($stack = []) {
	$matchrule = 'Power';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_162 = \null;
	do {
		$key = 'match_'.'Value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_162 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_161 = $result;
			$pos_161 = $this->pos;
			$key = 'match_'.'PowerOp'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else {
				$result = $res_161;
				$this->setPos($pos_161);
				unset($res_161, $pos_161);
				break;
			}
		}
		$_162 = \true; break;
	}
	while(\false);
	if($_162 === \true) { return $this->finalise($result); }
	if($_162 === \false) { return \false; }
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
	$_168 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_168 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Power'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_168 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_168 = \true; break;
	}
	while(\false);
	if($_168 === \true) { return $this->finalise($result); }
	if($_168 === \false) { return \false; }
}


/* UnaryPlus: '+' > operand:Power > */
protected $match_UnaryPlus_typestack = ['UnaryPlus'];
function match_UnaryPlus($stack = []) {
	$matchrule = 'UnaryPlus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_174 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_174 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Power'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_174 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_174 = \true; break;
	}
	while(\false);
	if($_174 === \true) { return $this->finalise($result); }
	if($_174 === \false) { return \false; }
}


/* Negation: '!' > operand:Power > */
protected $match_Negation_typestack = ['Negation'];
function match_Negation($stack = []) {
	$matchrule = 'Negation';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_180 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '!') {
			$this->addPos(1);
			$result["text"] .= '!';
		}
		else { $_180 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Power'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_180 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_180 = \true; break;
	}
	while(\false);
	if($_180 === \true) { return $this->finalise($result); }
	if($_180 === \false) { return \false; }
}


/* Unary: ( Negation | UnaryPlus | UnaryMinus | Power | Property ) */
protected $match_Unary_typestack = ['Unary'];
function match_Unary($stack = []) {
	$matchrule = 'Unary';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_199 = \null;
	do {
		$_197 = \null;
		do {
			$res_182 = $result;
			$pos_182 = $this->pos;
			$key = 'match_'.'Negation'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres);
				$_197 = \true; break;
			}
			$result = $res_182;
			$this->setPos($pos_182);
			$_195 = \null;
			do {
				$res_184 = $result;
				$pos_184 = $this->pos;
				$key = 'match_'.'UnaryPlus'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_195 = \true; break;
				}
				$result = $res_184;
				$this->setPos($pos_184);
				$_193 = \null;
				do {
					$res_186 = $result;
					$pos_186 = $this->pos;
					$key = 'match_'.'UnaryMinus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_193 = \true; break;
					}
					$result = $res_186;
					$this->setPos($pos_186);
					$_191 = \null;
					do {
						$res_188 = $result;
						$pos_188 = $this->pos;
						$key = 'match_'.'Power'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_191 = \true; break;
						}
						$result = $res_188;
						$this->setPos($pos_188);
						$key = 'match_'.'Property'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_191 = \true; break;
						}
						$result = $res_188;
						$this->setPos($pos_188);
						$_191 = \false; break;
					}
					while(\false);
					if($_191 === \true) { $_193 = \true; break; }
					$result = $res_186;
					$this->setPos($pos_186);
					$_193 = \false; break;
				}
				while(\false);
				if($_193 === \true) { $_195 = \true; break; }
				$result = $res_184;
				$this->setPos($pos_184);
				$_195 = \false; break;
			}
			while(\false);
			if($_195 === \true) { $_197 = \true; break; }
			$result = $res_182;
			$this->setPos($pos_182);
			$_197 = \false; break;
		}
		while(\false);
		if($_197 === \false) { $_199 = \false; break; }
		$_199 = \true; break;
	}
	while(\false);
	if($_199 === \true) { return $this->finalise($result); }
	if($_199 === \false) { return \false; }
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
	$_205 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '*') {
			$this->addPos(1);
			$result["text"] .= '*';
		}
		else { $_205 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_205 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_205 = \true; break;
	}
	while(\false);
	if($_205 === \true) { return $this->finalise($result); }
	if($_205 === \false) { return \false; }
}


/* Div: '/' > operand:Unary > */
protected $match_Div_typestack = ['Div'];
function match_Div($stack = []) {
	$matchrule = 'Div';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_211 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '/') {
			$this->addPos(1);
			$result["text"] .= '/';
		}
		else { $_211 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_211 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_211 = \true; break;
	}
	while(\false);
	if($_211 === \true) { return $this->finalise($result); }
	if($_211 === \false) { return \false; }
}


/* Mod: '%' > operand:Unary > */
protected $match_Mod_typestack = ['Mod'];
function match_Mod($stack = []) {
	$matchrule = 'Mod';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_217 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '%') {
			$this->addPos(1);
			$result["text"] .= '%';
		}
		else { $_217 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_217 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_217 = \true; break;
	}
	while(\false);
	if($_217 === \true) { return $this->finalise($result); }
	if($_217 === \false) { return \false; }
}


/* ImplicitTimes: operand:Power > */
protected $match_ImplicitTimes_typestack = ['ImplicitTimes'];
function match_ImplicitTimes($stack = []) {
	$matchrule = 'ImplicitTimes';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_221 = \null;
	do {
		$key = 'match_'.'Power'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_221 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_221 = \true; break;
	}
	while(\false);
	if($_221 === \true) { return $this->finalise($result); }
	if($_221 === \false) { return \false; }
}


/* Property: '[' > operand:Expr > ']' > */
protected $match_Property_typestack = ['Property'];
function match_Property($stack = []) {
	$matchrule = 'Property';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_229 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '[') {
			$this->addPos(1);
			$result["text"] .= '[';
		}
		else { $_229 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_229 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ']') {
			$this->addPos(1);
			$result["text"] .= ']';
		}
		else { $_229 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_229 = \true; break;
	}
	while(\false);
	if($_229 === \true) { return $this->finalise($result); }
	if($_229 === \false) { return \false; }
}


/* Product: Unary > ( Times | Property | ImplicitTimes | Div | Mod ) * */
protected $match_Product_typestack = ['Product'];
function match_Product($stack = []) {
	$matchrule = 'Product';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_252 = \null;
	do {
		$key = 'match_'.'Unary'; $pos = $this->pos;
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
					$res_233 = $result;
					$pos_233 = $this->pos;
					$key = 'match_'.'Times'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_248 = \true; break;
					}
					$result = $res_233;
					$this->setPos($pos_233);
					$_246 = \null;
					do {
						$res_235 = $result;
						$pos_235 = $this->pos;
						$key = 'match_'.'Property'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_246 = \true; break;
						}
						$result = $res_235;
						$this->setPos($pos_235);
						$_244 = \null;
						do {
							$res_237 = $result;
							$pos_237 = $this->pos;
							$key = 'match_'.'ImplicitTimes'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres);
								$_244 = \true; break;
							}
							$result = $res_237;
							$this->setPos($pos_237);
							$_242 = \null;
							do {
								$res_239 = $result;
								$pos_239 = $this->pos;
								$key = 'match_'.'Div'; $pos = $this->pos;
								$subres = $this->packhas($key, $pos)
									? $this->packread($key, $pos)
									: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
								if ($subres !== \false) {
									$this->store($result, $subres);
									$_242 = \true; break;
								}
								$result = $res_239;
								$this->setPos($pos_239);
								$key = 'match_'.'Mod'; $pos = $this->pos;
								$subres = $this->packhas($key, $pos)
									? $this->packread($key, $pos)
									: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
								if ($subres !== \false) {
									$this->store($result, $subres);
									$_242 = \true; break;
								}
								$result = $res_239;
								$this->setPos($pos_239);
								$_242 = \false; break;
							}
							while(\false);
							if($_242 === \true) { $_244 = \true; break; }
							$result = $res_237;
							$this->setPos($pos_237);
							$_244 = \false; break;
						}
						while(\false);
						if($_244 === \true) { $_246 = \true; break; }
						$result = $res_235;
						$this->setPos($pos_235);
						$_246 = \false; break;
					}
					while(\false);
					if($_246 === \true) { $_248 = \true; break; }
					$result = $res_233;
					$this->setPos($pos_233);
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
        $this->validate_types(['string', 'integer'], '[', $prop);
        $result['val'] = $this->with_type($object['value'][$prop['value']]);
    }

/* Plus: '+' > operand:Product > */
protected $match_Plus_typestack = ['Plus'];
function match_Plus($stack = []) {
	$matchrule = 'Plus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_258 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_258 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_258 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_258 = \true; break;
	}
	while(\false);
	if($_258 === \true) { return $this->finalise($result); }
	if($_258 === \false) { return \false; }
}


/* Minus: '-' > operand:Product > */
protected $match_Minus_typestack = ['Minus'];
function match_Minus($stack = []) {
	$matchrule = 'Minus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_264 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_264 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_264 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_264 = \true; break;
	}
	while(\false);
	if($_264 === \true) { return $this->finalise($result); }
	if($_264 === \false) { return \false; }
}


/* Sum: Product > ( Plus | Minus ) * */
protected $match_Sum_typestack = ['Sum'];
function match_Sum($stack = []) {
	$matchrule = 'Sum';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_275 = \null;
	do {
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_275 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_274 = $result;
			$pos_274 = $this->pos;
			$_273 = \null;
			do {
				$_271 = \null;
				do {
					$res_268 = $result;
					$pos_268 = $this->pos;
					$key = 'match_'.'Plus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_271 = \true; break;
					}
					$result = $res_268;
					$this->setPos($pos_268);
					$key = 'match_'.'Minus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_271 = \true; break;
					}
					$result = $res_268;
					$this->setPos($pos_268);
					$_271 = \false; break;
				}
				while(\false);
				if($_271 === \false) { $_273 = \false; break; }
				$_273 = \true; break;
			}
			while(\false);
			if($_273 === \false) {
				$result = $res_274;
				$this->setPos($pos_274);
				unset($res_274, $pos_274);
				break;
			}
		}
		$_275 = \true; break;
	}
	while(\false);
	if($_275 === \true) { return $this->finalise($result); }
	if($_275 === \false) { return \false; }
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
	$_282 = \null;
	do {
		$key = 'match_'.'Variable'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_282 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '=') {
			$this->addPos(1);
			$result["text"] .= '=';
		}
		else { $_282 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_282 = \false; break; }
		$_282 = \true; break;
	}
	while(\false);
	if($_282 === \true) { return $this->finalise($result); }
	if($_282 === \false) { return \false; }
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


/* FunctionAssignment: Name '(' > ( > Variable > ','? > ) * ')' > '=' > FunctionBody */
protected $match_FunctionAssignment_typestack = ['FunctionAssignment'];
function match_FunctionAssignment($stack = []) {
	$matchrule = 'FunctionAssignment';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_300 = \null;
	do {
		$key = 'match_'.'Name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_300 = \false; break; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_300 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_294 = $result;
			$pos_294 = $this->pos;
			$_293 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'Variable'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_293 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$res_291 = $result;
				$pos_291 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else {
					$result = $res_291;
					$this->setPos($pos_291);
					unset($res_291, $pos_291);
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_293 = \true; break;
			}
			while(\false);
			if($_293 === \false) {
				$result = $res_294;
				$this->setPos($pos_294);
				unset($res_294, $pos_294);
				break;
			}
		}
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_300 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '=') {
			$this->addPos(1);
			$result["text"] .= '=';
		}
		else { $_300 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'FunctionBody'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_300 = \false; break; }
		$_300 = \true; break;
	}
	while(\false);
	if($_300 === \true) { return $this->finalise($result); }
	if($_300 === \false) { return \false; }
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
	$_306 = \null;
	do {
		if (($subres = $this->literal('<<')) !== \false) { $result["text"] .= $subres; }
		else { $_306 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Sum'; $pos = $this->pos;
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


/* ShiftRight: '>>' > operand:Sum > */
protected $match_ShiftRight_typestack = ['ShiftRight'];
function match_ShiftRight($stack = []) {
	$matchrule = 'ShiftRight';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_312 = \null;
	do {
		if (($subres = $this->literal('>>')) !== \false) { $result["text"] .= $subres; }
		else { $_312 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Sum'; $pos = $this->pos;
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


/* BitShift: Sum > (ShiftRight | ShiftLeft) * */
protected $match_BitShift_typestack = ['BitShift'];
function match_BitShift($stack = []) {
	$matchrule = 'BitShift';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_323 = \null;
	do {
		$key = 'match_'.'Sum'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_323 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_322 = $result;
			$pos_322 = $this->pos;
			$_321 = \null;
			do {
				$_319 = \null;
				do {
					$res_316 = $result;
					$pos_316 = $this->pos;
					$key = 'match_'.'ShiftRight'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_319 = \true; break;
					}
					$result = $res_316;
					$this->setPos($pos_316);
					$key = 'match_'.'ShiftLeft'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_319 = \true; break;
					}
					$result = $res_316;
					$this->setPos($pos_316);
					$_319 = \false; break;
				}
				while(\false);
				if($_319 === \false) { $_321 = \false; break; }
				$_321 = \true; break;
			}
			while(\false);
			if($_321 === \false) {
				$result = $res_322;
				$this->setPos($pos_322);
				unset($res_322, $pos_322);
				break;
			}
		}
		$_323 = \true; break;
	}
	while(\false);
	if($_323 === \true) { return $this->finalise($result); }
	if($_323 === \false) { return \false; }
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
	$_329 = \null;
	do {
		if (($subres = $this->literal('===')) !== \false) { $result["text"] .= $subres; }
		else { $_329 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_329 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_329 = \true; break;
	}
	while(\false);
	if($_329 === \true) { return $this->finalise($result); }
	if($_329 === \false) { return \false; }
}


/* StrictNotEqual: '!==' > operand:BitShift > */
protected $match_StrictNotEqual_typestack = ['StrictNotEqual'];
function match_StrictNotEqual($stack = []) {
	$matchrule = 'StrictNotEqual';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_335 = \null;
	do {
		if (($subres = $this->literal('!==')) !== \false) { $result["text"] .= $subres; }
		else { $_335 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_335 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_335 = \true; break;
	}
	while(\false);
	if($_335 === \true) { return $this->finalise($result); }
	if($_335 === \false) { return \false; }
}


/* Equal: '==' > operand:BitShift > */
protected $match_Equal_typestack = ['Equal'];
function match_Equal($stack = []) {
	$matchrule = 'Equal';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_341 = \null;
	do {
		if (($subres = $this->literal('==')) !== \false) { $result["text"] .= $subres; }
		else { $_341 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_341 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_341 = \true; break;
	}
	while(\false);
	if($_341 === \true) { return $this->finalise($result); }
	if($_341 === \false) { return \false; }
}


/* Match: '=~' > operand:BitShift > */
protected $match_Match_typestack = ['Match'];
function match_Match($stack = []) {
	$matchrule = 'Match';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_347 = \null;
	do {
		if (($subres = $this->literal('=~')) !== \false) { $result["text"] .= $subres; }
		else { $_347 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_347 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_347 = \true; break;
	}
	while(\false);
	if($_347 === \true) { return $this->finalise($result); }
	if($_347 === \false) { return \false; }
}


/* NotEqual: '!=' > operand:BitShift > */
protected $match_NotEqual_typestack = ['NotEqual'];
function match_NotEqual($stack = []) {
	$matchrule = 'NotEqual';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_353 = \null;
	do {
		if (($subres = $this->literal('!=')) !== \false) { $result["text"] .= $subres; }
		else { $_353 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_353 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_353 = \true; break;
	}
	while(\false);
	if($_353 === \true) { return $this->finalise($result); }
	if($_353 === \false) { return \false; }
}


/* GreaterEqualThan: '>=' > operand:BitShift > */
protected $match_GreaterEqualThan_typestack = ['GreaterEqualThan'];
function match_GreaterEqualThan($stack = []) {
	$matchrule = 'GreaterEqualThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_359 = \null;
	do {
		if (($subres = $this->literal('>=')) !== \false) { $result["text"] .= $subres; }
		else { $_359 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_359 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_359 = \true; break;
	}
	while(\false);
	if($_359 === \true) { return $this->finalise($result); }
	if($_359 === \false) { return \false; }
}


/* LessEqualThan: '<=' > operand:BitShift > */
protected $match_LessEqualThan_typestack = ['LessEqualThan'];
function match_LessEqualThan($stack = []) {
	$matchrule = 'LessEqualThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_365 = \null;
	do {
		if (($subres = $this->literal('<=')) !== \false) { $result["text"] .= $subres; }
		else { $_365 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_365 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_365 = \true; break;
	}
	while(\false);
	if($_365 === \true) { return $this->finalise($result); }
	if($_365 === \false) { return \false; }
}


/* GreaterThan: '>' > operand:BitShift > */
protected $match_GreaterThan_typestack = ['GreaterThan'];
function match_GreaterThan($stack = []) {
	$matchrule = 'GreaterThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_371 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '>') {
			$this->addPos(1);
			$result["text"] .= '>';
		}
		else { $_371 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_371 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_371 = \true; break;
	}
	while(\false);
	if($_371 === \true) { return $this->finalise($result); }
	if($_371 === \false) { return \false; }
}


/* LessThan: '<' > operand:BitShift > */
protected $match_LessThan_typestack = ['LessThan'];
function match_LessThan($stack = []) {
	$matchrule = 'LessThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_377 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '<') {
			$this->addPos(1);
			$result["text"] .= '<';
		}
		else { $_377 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_377 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_377 = \true; break;
	}
	while(\false);
	if($_377 === \true) { return $this->finalise($result); }
	if($_377 === \false) { return \false; }
}


/* Compare: BitShift > (StrictEqual | Equal | Match | StrictNotEqual | NotEqual | GreaterEqualThan | GreaterThan | LessEqualThan | LessThan ) * */
protected $match_Compare_typestack = ['Compare'];
function match_Compare($stack = []) {
	$matchrule = 'Compare';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_416 = \null;
	do {
		$key = 'match_'.'BitShift'; $pos = $this->pos;
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
					$res_381 = $result;
					$pos_381 = $this->pos;
					$key = 'match_'.'StrictEqual'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_412 = \true; break;
					}
					$result = $res_381;
					$this->setPos($pos_381);
					$_410 = \null;
					do {
						$res_383 = $result;
						$pos_383 = $this->pos;
						$key = 'match_'.'Equal'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_410 = \true; break;
						}
						$result = $res_383;
						$this->setPos($pos_383);
						$_408 = \null;
						do {
							$res_385 = $result;
							$pos_385 = $this->pos;
							$key = 'match_'.'Match'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres);
								$_408 = \true; break;
							}
							$result = $res_385;
							$this->setPos($pos_385);
							$_406 = \null;
							do {
								$res_387 = $result;
								$pos_387 = $this->pos;
								$key = 'match_'.'StrictNotEqual'; $pos = $this->pos;
								$subres = $this->packhas($key, $pos)
									? $this->packread($key, $pos)
									: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
								if ($subres !== \false) {
									$this->store($result, $subres);
									$_406 = \true; break;
								}
								$result = $res_387;
								$this->setPos($pos_387);
								$_404 = \null;
								do {
									$res_389 = $result;
									$pos_389 = $this->pos;
									$key = 'match_'.'NotEqual'; $pos = $this->pos;
									$subres = $this->packhas($key, $pos)
										? $this->packread($key, $pos)
										: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
									if ($subres !== \false) {
										$this->store($result, $subres);
										$_404 = \true; break;
									}
									$result = $res_389;
									$this->setPos($pos_389);
									$_402 = \null;
									do {
										$res_391 = $result;
										$pos_391 = $this->pos;
										$key = 'match_'.'GreaterEqualThan'; $pos = $this->pos;
										$subres = $this->packhas($key, $pos)
											? $this->packread($key, $pos)
											: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
										if ($subres !== \false) {
											$this->store($result, $subres);
											$_402 = \true; break;
										}
										$result = $res_391;
										$this->setPos($pos_391);
										$_400 = \null;
										do {
											$res_393 = $result;
											$pos_393 = $this->pos;
											$key = 'match_'.'GreaterThan'; $pos = $this->pos;
											$subres = $this->packhas($key, $pos)
												? $this->packread($key, $pos)
												: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
											if ($subres !== \false) {
												$this->store($result, $subres);
												$_400 = \true; break;
											}
											$result = $res_393;
											$this->setPos($pos_393);
											$_398 = \null;
											do {
												$res_395 = $result;
												$pos_395 = $this->pos;
												$key = 'match_'.'LessEqualThan'; $pos = $this->pos;
												$subres = $this->packhas($key, $pos)
													? $this->packread($key, $pos)
													: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
												if ($subres !== \false) {
													$this->store($result, $subres);
													$_398 = \true; break;
												}
												$result = $res_395;
												$this->setPos($pos_395);
												$key = 'match_'.'LessThan'; $pos = $this->pos;
												$subres = $this->packhas($key, $pos)
													? $this->packread($key, $pos)
													: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
												if ($subres !== \false) {
													$this->store($result, $subres);
													$_398 = \true; break;
												}
												$result = $res_395;
												$this->setPos($pos_395);
												$_398 = \false; break;
											}
											while(\false);
											if($_398 === \true) { $_400 = \true; break; }
											$result = $res_393;
											$this->setPos($pos_393);
											$_400 = \false; break;
										}
										while(\false);
										if($_400 === \true) { $_402 = \true; break; }
										$result = $res_391;
										$this->setPos($pos_391);
										$_402 = \false; break;
									}
									while(\false);
									if($_402 === \true) { $_404 = \true; break; }
									$result = $res_389;
									$this->setPos($pos_389);
									$_404 = \false; break;
								}
								while(\false);
								if($_404 === \true) { $_406 = \true; break; }
								$result = $res_387;
								$this->setPos($pos_387);
								$_406 = \false; break;
							}
							while(\false);
							if($_406 === \true) { $_408 = \true; break; }
							$result = $res_385;
							$this->setPos($pos_385);
							$_408 = \false; break;
						}
						while(\false);
						if($_408 === \true) { $_410 = \true; break; }
						$result = $res_383;
						$this->setPos($pos_383);
						$_410 = \false; break;
					}
					while(\false);
					if($_410 === \true) { $_412 = \true; break; }
					$result = $res_381;
					$this->setPos($pos_381);
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
	$_422 = \null;
	do {
		if (($subres = $this->literal('&&')) !== \false) { $result["text"] .= $subres; }
		else { $_422 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_422 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_422 = \true; break;
	}
	while(\false);
	if($_422 === \true) { return $this->finalise($result); }
	if($_422 === \false) { return \false; }
}


/* Or: '||' > operand:Compare > */
protected $match_Or_typestack = ['Or'];
function match_Or($stack = []) {
	$matchrule = 'Or';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_428 = \null;
	do {
		if (($subres = $this->literal('||')) !== \false) { $result["text"] .= $subres; }
		else { $_428 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_428 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_428 = \true; break;
	}
	while(\false);
	if($_428 === \true) { return $this->finalise($result); }
	if($_428 === \false) { return \false; }
}


/* Boolean: Compare > (And | Or ) * */
protected $match_Boolean_typestack = ['Boolean'];
function match_Boolean($stack = []) {
	$matchrule = 'Boolean';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_439 = \null;
	do {
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_439 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_438 = $result;
			$pos_438 = $this->pos;
			$_437 = \null;
			do {
				$_435 = \null;
				do {
					$res_432 = $result;
					$pos_432 = $this->pos;
					$key = 'match_'.'And'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_435 = \true; break;
					}
					$result = $res_432;
					$this->setPos($pos_432);
					$key = 'match_'.'Or'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_435 = \true; break;
					}
					$result = $res_432;
					$this->setPos($pos_432);
					$_435 = \false; break;
				}
				while(\false);
				if($_435 === \false) { $_437 = \false; break; }
				$_437 = \true; break;
			}
			while(\false);
			if($_437 === \false) {
				$result = $res_438;
				$this->setPos($pos_438);
				unset($res_438, $pos_438);
				break;
			}
		}
		$_439 = \true; break;
	}
	while(\false);
	if($_439 === \true) { return $this->finalise($result); }
	if($_439 === \false) { return \false; }
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
	$_454 = \null;
	do {
		$_451 = \null;
		do {
			$_449 = \null;
			do {
				$res_442 = $result;
				$pos_442 = $this->pos;
				$key = 'match_'.'VariableAssignment'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_449 = \true; break;
				}
				$result = $res_442;
				$this->setPos($pos_442);
				$_447 = \null;
				do {
					$res_444 = $result;
					$pos_444 = $this->pos;
					$key = 'match_'.'FunctionAssignment'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_447 = \true; break;
					}
					$result = $res_444;
					$this->setPos($pos_444);
					$key = 'match_'.'Expr'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_447 = \true; break;
					}
					$result = $res_444;
					$this->setPos($pos_444);
					$_447 = \false; break;
				}
				while(\false);
				if($_447 === \true) { $_449 = \true; break; }
				$result = $res_442;
				$this->setPos($pos_442);
				$_449 = \false; break;
			}
			while(\false);
			if($_449 === \false) { $_451 = \false; break; }
			$_451 = \true; break;
		}
		while(\false);
		if($_451 === \false) { $_454 = \false; break; }
		$res_453 = $result;
		$pos_453 = $this->pos;
		if (\substr($this->string, $this->pos, 1) === ';') {
			$this->addPos(1);
			$result["text"] .= ';';
		}
		else {
			$result = $res_453;
			$this->setPos($pos_453);
			unset($res_453, $pos_453);
		}
		$_454 = \true; break;
	}
	while(\false);
	if($_454 === \true) { return $this->finalise($result); }
	if($_454 === \false) { return \false; }
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

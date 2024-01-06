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

/* Value: FunctionCall > | VariableReference > | SimpleValue > | '(' > Expr > ')' > */
protected $match_Value_typestack = ['Value'];
function match_Value($stack = []) {
	$matchrule = 'Value';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_121 = \null;
	do {
		$res_94 = $result;
		$pos_94 = $this->pos;
		$_97 = \null;
		do {
			$key = 'match_'.'FunctionCall'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else { $_97 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$_97 = \true; break;
		}
		while(\false);
		if($_97 === \true) { $_121 = \true; break; }
		$result = $res_94;
		$this->setPos($pos_94);
		$_119 = \null;
		do {
			$res_99 = $result;
			$pos_99 = $this->pos;
			$_102 = \null;
			do {
				$key = 'match_'.'VariableReference'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_102 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_102 = \true; break;
			}
			while(\false);
			if($_102 === \true) { $_119 = \true; break; }
			$result = $res_99;
			$this->setPos($pos_99);
			$_117 = \null;
			do {
				$res_104 = $result;
				$pos_104 = $this->pos;
				$_107 = \null;
				do {
					$key = 'match_'.'SimpleValue'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) { $this->store($result, $subres); }
					else { $_107 = \false; break; }
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					$_107 = \true; break;
				}
				while(\false);
				if($_107 === \true) { $_117 = \true; break; }
				$result = $res_104;
				$this->setPos($pos_104);
				$_115 = \null;
				do {
					if (\substr($this->string, $this->pos, 1) === '(') {
						$this->addPos(1);
						$result["text"] .= '(';
					}
					else { $_115 = \false; break; }
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					$key = 'match_'.'Expr'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) { $this->store($result, $subres); }
					else { $_115 = \false; break; }
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
				if($_115 === \true) { $_117 = \true; break; }
				$result = $res_104;
				$this->setPos($pos_104);
				$_117 = \false; break;
			}
			while(\false);
			if($_117 === \true) { $_119 = \true; break; }
			$result = $res_99;
			$this->setPos($pos_99);
			$_119 = \false; break;
		}
		while(\false);
		if($_119 === \true) { $_121 = \true; break; }
		$result = $res_94;
		$this->setPos($pos_94);
		$_121 = \false; break;
	}
	while(\false);
	if($_121 === \true) { return $this->finalise($result); }
	if($_121 === \false) { return \false; }
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
	$_136 = \null;
	do {
		$key = 'match_'.'Name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_136 = \false; break; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_136 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_132 = $result;
			$pos_132 = $this->pos;
			$_131 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'Expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_131 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$res_129 = $result;
				$pos_129 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else {
					$result = $res_129;
					$this->setPos($pos_129);
					unset($res_129, $pos_129);
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_131 = \true; break;
			}
			while(\false);
			if($_131 === \false) {
				$result = $res_132;
				$this->setPos($pos_132);
				unset($res_132, $pos_132);
				break;
			}
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_136 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_136 = \true; break;
	}
	while(\false);
	if($_136 === \true) { return $this->finalise($result); }
	if($_136 === \false) { return \false; }
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
	$_149 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "op");
		$_144 = \null;
		do {
			$_142 = \null;
			do {
				$res_139 = $result;
				$pos_139 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === '^') {
					$this->addPos(1);
					$result["text"] .= '^';
					$_142 = \true; break;
				}
				$result = $res_139;
				$this->setPos($pos_139);
				if (($subres = $this->literal('**')) !== \false) {
					$result["text"] .= $subres;
					$_142 = \true; break;
				}
				$result = $res_139;
				$this->setPos($pos_139);
				$_142 = \false; break;
			}
			while(\false);
			if($_142 === \false) { $_144 = \false; break; }
			$_144 = \true; break;
		}
		while(\false);
		if($_144 === \true) {
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'op');
		}
		if($_144 === \false) {
			$result = \array_pop($stack);
			$_149 = \false; break;
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Value'; $pos = $this->pos;
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


/* Power: Value > PowerOp * */
protected $match_Power_typestack = ['Power'];
function match_Power($stack = []) {
	$matchrule = 'Power';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_154 = \null;
	do {
		$key = 'match_'.'Value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_154 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_153 = $result;
			$pos_153 = $this->pos;
			$key = 'match_'.'PowerOp'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else {
				$result = $res_153;
				$this->setPos($pos_153);
				unset($res_153, $pos_153);
				break;
			}
		}
		$_154 = \true; break;
	}
	while(\false);
	if($_154 === \true) { return $this->finalise($result); }
	if($_154 === \false) { return \false; }
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
	$_160 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_160 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Power'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_160 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_160 = \true; break;
	}
	while(\false);
	if($_160 === \true) { return $this->finalise($result); }
	if($_160 === \false) { return \false; }
}


/* UnaryPlus: '+' > operand:Power > */
protected $match_UnaryPlus_typestack = ['UnaryPlus'];
function match_UnaryPlus($stack = []) {
	$matchrule = 'UnaryPlus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_166 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_166 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Power'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_166 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_166 = \true; break;
	}
	while(\false);
	if($_166 === \true) { return $this->finalise($result); }
	if($_166 === \false) { return \false; }
}


/* Negation: '!' > operand:Power > */
protected $match_Negation_typestack = ['Negation'];
function match_Negation($stack = []) {
	$matchrule = 'Negation';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_172 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '!') {
			$this->addPos(1);
			$result["text"] .= '!';
		}
		else { $_172 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Power'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_172 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_172 = \true; break;
	}
	while(\false);
	if($_172 === \true) { return $this->finalise($result); }
	if($_172 === \false) { return \false; }
}


/* Unary: ( Negation | UnaryPlus | UnaryMinus | Power ) */
protected $match_Unary_typestack = ['Unary'];
function match_Unary($stack = []) {
	$matchrule = 'Unary';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_187 = \null;
	do {
		$_185 = \null;
		do {
			$res_174 = $result;
			$pos_174 = $this->pos;
			$key = 'match_'.'Negation'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres);
				$_185 = \true; break;
			}
			$result = $res_174;
			$this->setPos($pos_174);
			$_183 = \null;
			do {
				$res_176 = $result;
				$pos_176 = $this->pos;
				$key = 'match_'.'UnaryPlus'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_183 = \true; break;
				}
				$result = $res_176;
				$this->setPos($pos_176);
				$_181 = \null;
				do {
					$res_178 = $result;
					$pos_178 = $this->pos;
					$key = 'match_'.'UnaryMinus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_181 = \true; break;
					}
					$result = $res_178;
					$this->setPos($pos_178);
					$key = 'match_'.'Power'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_181 = \true; break;
					}
					$result = $res_178;
					$this->setPos($pos_178);
					$_181 = \false; break;
				}
				while(\false);
				if($_181 === \true) { $_183 = \true; break; }
				$result = $res_176;
				$this->setPos($pos_176);
				$_183 = \false; break;
			}
			while(\false);
			if($_183 === \true) { $_185 = \true; break; }
			$result = $res_174;
			$this->setPos($pos_174);
			$_185 = \false; break;
		}
		while(\false);
		if($_185 === \false) { $_187 = \false; break; }
		$_187 = \true; break;
	}
	while(\false);
	if($_187 === \true) { return $this->finalise($result); }
	if($_187 === \false) { return \false; }
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
	$_193 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '*') {
			$this->addPos(1);
			$result["text"] .= '*';
		}
		else { $_193 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_193 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_193 = \true; break;
	}
	while(\false);
	if($_193 === \true) { return $this->finalise($result); }
	if($_193 === \false) { return \false; }
}


/* Div: '/' > operand:Unary > */
protected $match_Div_typestack = ['Div'];
function match_Div($stack = []) {
	$matchrule = 'Div';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_199 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '/') {
			$this->addPos(1);
			$result["text"] .= '/';
		}
		else { $_199 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_199 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_199 = \true; break;
	}
	while(\false);
	if($_199 === \true) { return $this->finalise($result); }
	if($_199 === \false) { return \false; }
}


/* Mod: '%' > operand:Unary > */
protected $match_Mod_typestack = ['Mod'];
function match_Mod($stack = []) {
	$matchrule = 'Mod';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_205 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '%') {
			$this->addPos(1);
			$result["text"] .= '%';
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


/* ImplicitTimes: operand:Power > */
protected $match_ImplicitTimes_typestack = ['ImplicitTimes'];
function match_ImplicitTimes($stack = []) {
	$matchrule = 'ImplicitTimes';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_209 = \null;
	do {
		$key = 'match_'.'Power'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_209 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_209 = \true; break;
	}
	while(\false);
	if($_209 === \true) { return $this->finalise($result); }
	if($_209 === \false) { return \false; }
}


/* Product: Unary > ( Times | ImplicitTimes | Div | Mod ) * */
protected $match_Product_typestack = ['Product'];
function match_Product($stack = []) {
	$matchrule = 'Product';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_228 = \null;
	do {
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_228 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_227 = $result;
			$pos_227 = $this->pos;
			$_226 = \null;
			do {
				$_224 = \null;
				do {
					$res_213 = $result;
					$pos_213 = $this->pos;
					$key = 'match_'.'Times'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_224 = \true; break;
					}
					$result = $res_213;
					$this->setPos($pos_213);
					$_222 = \null;
					do {
						$res_215 = $result;
						$pos_215 = $this->pos;
						$key = 'match_'.'ImplicitTimes'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_222 = \true; break;
						}
						$result = $res_215;
						$this->setPos($pos_215);
						$_220 = \null;
						do {
							$res_217 = $result;
							$pos_217 = $this->pos;
							$key = 'match_'.'Div'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres);
								$_220 = \true; break;
							}
							$result = $res_217;
							$this->setPos($pos_217);
							$key = 'match_'.'Mod'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres);
								$_220 = \true; break;
							}
							$result = $res_217;
							$this->setPos($pos_217);
							$_220 = \false; break;
						}
						while(\false);
						if($_220 === \true) { $_222 = \true; break; }
						$result = $res_215;
						$this->setPos($pos_215);
						$_222 = \false; break;
					}
					while(\false);
					if($_222 === \true) { $_224 = \true; break; }
					$result = $res_213;
					$this->setPos($pos_213);
					$_224 = \false; break;
				}
				while(\false);
				if($_224 === \false) { $_226 = \false; break; }
				$_226 = \true; break;
			}
			while(\false);
			if($_226 === \false) {
				$result = $res_227;
				$this->setPos($pos_227);
				unset($res_227, $pos_227);
				break;
			}
		}
		$_228 = \true; break;
	}
	while(\false);
	if($_228 === \true) { return $this->finalise($result); }
	if($_228 === \false) { return \false; }
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

/* Plus: '+' > operand:Product > */
protected $match_Plus_typestack = ['Plus'];
function match_Plus($stack = []) {
	$matchrule = 'Plus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_234 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_234 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_234 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_234 = \true; break;
	}
	while(\false);
	if($_234 === \true) { return $this->finalise($result); }
	if($_234 === \false) { return \false; }
}


/* Minus: '-' > operand:Product > */
protected $match_Minus_typestack = ['Minus'];
function match_Minus($stack = []) {
	$matchrule = 'Minus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_240 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_240 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_240 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_240 = \true; break;
	}
	while(\false);
	if($_240 === \true) { return $this->finalise($result); }
	if($_240 === \false) { return \false; }
}


/* Sum: Product > ( Plus | Minus ) * */
protected $match_Sum_typestack = ['Sum'];
function match_Sum($stack = []) {
	$matchrule = 'Sum';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_251 = \null;
	do {
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_251 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_250 = $result;
			$pos_250 = $this->pos;
			$_249 = \null;
			do {
				$_247 = \null;
				do {
					$res_244 = $result;
					$pos_244 = $this->pos;
					$key = 'match_'.'Plus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_247 = \true; break;
					}
					$result = $res_244;
					$this->setPos($pos_244);
					$key = 'match_'.'Minus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_247 = \true; break;
					}
					$result = $res_244;
					$this->setPos($pos_244);
					$_247 = \false; break;
				}
				while(\false);
				if($_247 === \false) { $_249 = \false; break; }
				$_249 = \true; break;
			}
			while(\false);
			if($_249 === \false) {
				$result = $res_250;
				$this->setPos($pos_250);
				unset($res_250, $pos_250);
				break;
			}
		}
		$_251 = \true; break;
	}
	while(\false);
	if($_251 === \true) { return $this->finalise($result); }
	if($_251 === \false) { return \false; }
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
	$_258 = \null;
	do {
		$key = 'match_'.'Variable'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_258 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '=') {
			$this->addPos(1);
			$result["text"] .= '=';
		}
		else { $_258 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_258 = \false; break; }
		$_258 = \true; break;
	}
	while(\false);
	if($_258 === \true) { return $this->finalise($result); }
	if($_258 === \false) { return \false; }
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
	$_276 = \null;
	do {
		$key = 'match_'.'Name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_276 = \false; break; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_276 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_270 = $result;
			$pos_270 = $this->pos;
			$_269 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'Variable'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_269 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$res_267 = $result;
				$pos_267 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else {
					$result = $res_267;
					$this->setPos($pos_267);
					unset($res_267, $pos_267);
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_269 = \true; break;
			}
			while(\false);
			if($_269 === \false) {
				$result = $res_270;
				$this->setPos($pos_270);
				unset($res_270, $pos_270);
				break;
			}
		}
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_276 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '=') {
			$this->addPos(1);
			$result["text"] .= '=';
		}
		else { $_276 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'FunctionBody'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_276 = \false; break; }
		$_276 = \true; break;
	}
	while(\false);
	if($_276 === \true) { return $this->finalise($result); }
	if($_276 === \false) { return \false; }
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
	$_282 = \null;
	do {
		if (($subres = $this->literal('<<')) !== \false) { $result["text"] .= $subres; }
		else { $_282 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Sum'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_282 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_282 = \true; break;
	}
	while(\false);
	if($_282 === \true) { return $this->finalise($result); }
	if($_282 === \false) { return \false; }
}


/* ShiftRight: '>>' > operand:Sum > */
protected $match_ShiftRight_typestack = ['ShiftRight'];
function match_ShiftRight($stack = []) {
	$matchrule = 'ShiftRight';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_288 = \null;
	do {
		if (($subres = $this->literal('>>')) !== \false) { $result["text"] .= $subres; }
		else { $_288 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Sum'; $pos = $this->pos;
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


/* BitShift: Sum > (ShiftRight | ShiftLeft) * */
protected $match_BitShift_typestack = ['BitShift'];
function match_BitShift($stack = []) {
	$matchrule = 'BitShift';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_299 = \null;
	do {
		$key = 'match_'.'Sum'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_299 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_298 = $result;
			$pos_298 = $this->pos;
			$_297 = \null;
			do {
				$_295 = \null;
				do {
					$res_292 = $result;
					$pos_292 = $this->pos;
					$key = 'match_'.'ShiftRight'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_295 = \true; break;
					}
					$result = $res_292;
					$this->setPos($pos_292);
					$key = 'match_'.'ShiftLeft'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_295 = \true; break;
					}
					$result = $res_292;
					$this->setPos($pos_292);
					$_295 = \false; break;
				}
				while(\false);
				if($_295 === \false) { $_297 = \false; break; }
				$_297 = \true; break;
			}
			while(\false);
			if($_297 === \false) {
				$result = $res_298;
				$this->setPos($pos_298);
				unset($res_298, $pos_298);
				break;
			}
		}
		$_299 = \true; break;
	}
	while(\false);
	if($_299 === \true) { return $this->finalise($result); }
	if($_299 === \false) { return \false; }
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
	$_305 = \null;
	do {
		if (($subres = $this->literal('===')) !== \false) { $result["text"] .= $subres; }
		else { $_305 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_305 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_305 = \true; break;
	}
	while(\false);
	if($_305 === \true) { return $this->finalise($result); }
	if($_305 === \false) { return \false; }
}


/* StrictNotEqual: '!==' > operand:BitShift > */
protected $match_StrictNotEqual_typestack = ['StrictNotEqual'];
function match_StrictNotEqual($stack = []) {
	$matchrule = 'StrictNotEqual';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_311 = \null;
	do {
		if (($subres = $this->literal('!==')) !== \false) { $result["text"] .= $subres; }
		else { $_311 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_311 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_311 = \true; break;
	}
	while(\false);
	if($_311 === \true) { return $this->finalise($result); }
	if($_311 === \false) { return \false; }
}


/* Equal: '==' > operand:BitShift > */
protected $match_Equal_typestack = ['Equal'];
function match_Equal($stack = []) {
	$matchrule = 'Equal';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_317 = \null;
	do {
		if (($subres = $this->literal('==')) !== \false) { $result["text"] .= $subres; }
		else { $_317 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_317 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_317 = \true; break;
	}
	while(\false);
	if($_317 === \true) { return $this->finalise($result); }
	if($_317 === \false) { return \false; }
}


/* Match: '=~' > operand:BitShift > */
protected $match_Match_typestack = ['Match'];
function match_Match($stack = []) {
	$matchrule = 'Match';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_323 = \null;
	do {
		if (($subres = $this->literal('=~')) !== \false) { $result["text"] .= $subres; }
		else { $_323 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_323 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_323 = \true; break;
	}
	while(\false);
	if($_323 === \true) { return $this->finalise($result); }
	if($_323 === \false) { return \false; }
}


/* NotEqual: '!=' > operand:BitShift > */
protected $match_NotEqual_typestack = ['NotEqual'];
function match_NotEqual($stack = []) {
	$matchrule = 'NotEqual';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_329 = \null;
	do {
		if (($subres = $this->literal('!=')) !== \false) { $result["text"] .= $subres; }
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


/* GreaterEqualThan: '>=' > operand:BitShift > */
protected $match_GreaterEqualThan_typestack = ['GreaterEqualThan'];
function match_GreaterEqualThan($stack = []) {
	$matchrule = 'GreaterEqualThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_335 = \null;
	do {
		if (($subres = $this->literal('>=')) !== \false) { $result["text"] .= $subres; }
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


/* LessEqualThan: '<=' > operand:BitShift > */
protected $match_LessEqualThan_typestack = ['LessEqualThan'];
function match_LessEqualThan($stack = []) {
	$matchrule = 'LessEqualThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_341 = \null;
	do {
		if (($subres = $this->literal('<=')) !== \false) { $result["text"] .= $subres; }
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


/* GreaterThan: '>' > operand:BitShift > */
protected $match_GreaterThan_typestack = ['GreaterThan'];
function match_GreaterThan($stack = []) {
	$matchrule = 'GreaterThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_347 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '>') {
			$this->addPos(1);
			$result["text"] .= '>';
		}
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


/* LessThan: '<' > operand:BitShift > */
protected $match_LessThan_typestack = ['LessThan'];
function match_LessThan($stack = []) {
	$matchrule = 'LessThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_353 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '<') {
			$this->addPos(1);
			$result["text"] .= '<';
		}
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


/* Compare: BitShift > (StrictEqual | Equal | Match | StrictNotEqual | NotEqual | GreaterEqualThan | GreaterThan | LessEqualThan | LessThan ) * */
protected $match_Compare_typestack = ['Compare'];
function match_Compare($stack = []) {
	$matchrule = 'Compare';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_392 = \null;
	do {
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_392 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_391 = $result;
			$pos_391 = $this->pos;
			$_390 = \null;
			do {
				$_388 = \null;
				do {
					$res_357 = $result;
					$pos_357 = $this->pos;
					$key = 'match_'.'StrictEqual'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_388 = \true; break;
					}
					$result = $res_357;
					$this->setPos($pos_357);
					$_386 = \null;
					do {
						$res_359 = $result;
						$pos_359 = $this->pos;
						$key = 'match_'.'Equal'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_386 = \true; break;
						}
						$result = $res_359;
						$this->setPos($pos_359);
						$_384 = \null;
						do {
							$res_361 = $result;
							$pos_361 = $this->pos;
							$key = 'match_'.'Match'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres);
								$_384 = \true; break;
							}
							$result = $res_361;
							$this->setPos($pos_361);
							$_382 = \null;
							do {
								$res_363 = $result;
								$pos_363 = $this->pos;
								$key = 'match_'.'StrictNotEqual'; $pos = $this->pos;
								$subres = $this->packhas($key, $pos)
									? $this->packread($key, $pos)
									: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
								if ($subres !== \false) {
									$this->store($result, $subres);
									$_382 = \true; break;
								}
								$result = $res_363;
								$this->setPos($pos_363);
								$_380 = \null;
								do {
									$res_365 = $result;
									$pos_365 = $this->pos;
									$key = 'match_'.'NotEqual'; $pos = $this->pos;
									$subres = $this->packhas($key, $pos)
										? $this->packread($key, $pos)
										: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
									if ($subres !== \false) {
										$this->store($result, $subres);
										$_380 = \true; break;
									}
									$result = $res_365;
									$this->setPos($pos_365);
									$_378 = \null;
									do {
										$res_367 = $result;
										$pos_367 = $this->pos;
										$key = 'match_'.'GreaterEqualThan'; $pos = $this->pos;
										$subres = $this->packhas($key, $pos)
											? $this->packread($key, $pos)
											: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
										if ($subres !== \false) {
											$this->store($result, $subres);
											$_378 = \true; break;
										}
										$result = $res_367;
										$this->setPos($pos_367);
										$_376 = \null;
										do {
											$res_369 = $result;
											$pos_369 = $this->pos;
											$key = 'match_'.'GreaterThan'; $pos = $this->pos;
											$subres = $this->packhas($key, $pos)
												? $this->packread($key, $pos)
												: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
											if ($subres !== \false) {
												$this->store($result, $subres);
												$_376 = \true; break;
											}
											$result = $res_369;
											$this->setPos($pos_369);
											$_374 = \null;
											do {
												$res_371 = $result;
												$pos_371 = $this->pos;
												$key = 'match_'.'LessEqualThan'; $pos = $this->pos;
												$subres = $this->packhas($key, $pos)
													? $this->packread($key, $pos)
													: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
												if ($subres !== \false) {
													$this->store($result, $subres);
													$_374 = \true; break;
												}
												$result = $res_371;
												$this->setPos($pos_371);
												$key = 'match_'.'LessThan'; $pos = $this->pos;
												$subres = $this->packhas($key, $pos)
													? $this->packread($key, $pos)
													: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
												if ($subres !== \false) {
													$this->store($result, $subres);
													$_374 = \true; break;
												}
												$result = $res_371;
												$this->setPos($pos_371);
												$_374 = \false; break;
											}
											while(\false);
											if($_374 === \true) { $_376 = \true; break; }
											$result = $res_369;
											$this->setPos($pos_369);
											$_376 = \false; break;
										}
										while(\false);
										if($_376 === \true) { $_378 = \true; break; }
										$result = $res_367;
										$this->setPos($pos_367);
										$_378 = \false; break;
									}
									while(\false);
									if($_378 === \true) { $_380 = \true; break; }
									$result = $res_365;
									$this->setPos($pos_365);
									$_380 = \false; break;
								}
								while(\false);
								if($_380 === \true) { $_382 = \true; break; }
								$result = $res_363;
								$this->setPos($pos_363);
								$_382 = \false; break;
							}
							while(\false);
							if($_382 === \true) { $_384 = \true; break; }
							$result = $res_361;
							$this->setPos($pos_361);
							$_384 = \false; break;
						}
						while(\false);
						if($_384 === \true) { $_386 = \true; break; }
						$result = $res_359;
						$this->setPos($pos_359);
						$_386 = \false; break;
					}
					while(\false);
					if($_386 === \true) { $_388 = \true; break; }
					$result = $res_357;
					$this->setPos($pos_357);
					$_388 = \false; break;
				}
				while(\false);
				if($_388 === \false) { $_390 = \false; break; }
				$_390 = \true; break;
			}
			while(\false);
			if($_390 === \false) {
				$result = $res_391;
				$this->setPos($pos_391);
				unset($res_391, $pos_391);
				break;
			}
		}
		$_392 = \true; break;
	}
	while(\false);
	if($_392 === \true) { return $this->finalise($result); }
	if($_392 === \false) { return \false; }
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

/* And: '&&' > operand:Compare > */
protected $match_And_typestack = ['And'];
function match_And($stack = []) {
	$matchrule = 'And';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_398 = \null;
	do {
		if (($subres = $this->literal('&&')) !== \false) { $result["text"] .= $subres; }
		else { $_398 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_398 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_398 = \true; break;
	}
	while(\false);
	if($_398 === \true) { return $this->finalise($result); }
	if($_398 === \false) { return \false; }
}


/* Or: '||' > operand:Compare > */
protected $match_Or_typestack = ['Or'];
function match_Or($stack = []) {
	$matchrule = 'Or';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_404 = \null;
	do {
		if (($subres = $this->literal('||')) !== \false) { $result["text"] .= $subres; }
		else { $_404 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_404 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_404 = \true; break;
	}
	while(\false);
	if($_404 === \true) { return $this->finalise($result); }
	if($_404 === \false) { return \false; }
}


/* Boolean: Compare > (And | Or ) * */
protected $match_Boolean_typestack = ['Boolean'];
function match_Boolean($stack = []) {
	$matchrule = 'Boolean';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_415 = \null;
	do {
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_415 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_414 = $result;
			$pos_414 = $this->pos;
			$_413 = \null;
			do {
				$_411 = \null;
				do {
					$res_408 = $result;
					$pos_408 = $this->pos;
					$key = 'match_'.'And'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_411 = \true; break;
					}
					$result = $res_408;
					$this->setPos($pos_408);
					$key = 'match_'.'Or'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_411 = \true; break;
					}
					$result = $res_408;
					$this->setPos($pos_408);
					$_411 = \false; break;
				}
				while(\false);
				if($_411 === \false) { $_413 = \false; break; }
				$_413 = \true; break;
			}
			while(\false);
			if($_413 === \false) {
				$result = $res_414;
				$this->setPos($pos_414);
				unset($res_414, $pos_414);
				break;
			}
		}
		$_415 = \true; break;
	}
	while(\false);
	if($_415 === \true) { return $this->finalise($result); }
	if($_415 === \false) { return \false; }
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
	$_430 = \null;
	do {
		$_427 = \null;
		do {
			$_425 = \null;
			do {
				$res_418 = $result;
				$pos_418 = $this->pos;
				$key = 'match_'.'VariableAssignment'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_425 = \true; break;
				}
				$result = $res_418;
				$this->setPos($pos_418);
				$_423 = \null;
				do {
					$res_420 = $result;
					$pos_420 = $this->pos;
					$key = 'match_'.'FunctionAssignment'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_423 = \true; break;
					}
					$result = $res_420;
					$this->setPos($pos_420);
					$key = 'match_'.'Expr'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_423 = \true; break;
					}
					$result = $res_420;
					$this->setPos($pos_420);
					$_423 = \false; break;
				}
				while(\false);
				if($_423 === \true) { $_425 = \true; break; }
				$result = $res_418;
				$this->setPos($pos_418);
				$_425 = \false; break;
			}
			while(\false);
			if($_425 === \false) { $_427 = \false; break; }
			$_427 = \true; break;
		}
		while(\false);
		if($_427 === \false) { $_430 = \false; break; }
		$res_429 = $result;
		$pos_429 = $this->pos;
		if (\substr($this->string, $this->pos, 1) === ';') {
			$this->addPos(1);
			$result["text"] .= ';';
		}
		else {
			$result = $res_429;
			$this->setPos($pos_429);
			unset($res_429, $pos_429);
		}
		$_430 = \true; break;
	}
	while(\false);
	if($_430 === \true) { return $this->finalise($result); }
	if($_430 === \false) { return \false; }
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

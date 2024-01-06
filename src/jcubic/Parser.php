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

/* SingleQuoted: q:/'/ ( /\\{2}/ * /\\'/ | /[^']/ ) * '$q' */
protected $match_SingleQuoted_typestack = ['SingleQuoted'];
function match_SingleQuoted($stack = []) {
	$matchrule = 'SingleQuoted';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_27 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "q");
		if (($subres = $this->rx('/\'/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'q');
		}
		else {
			$result = \array_pop($stack);
			$_27 = \false; break;
		}
		while (\true) {
			$res_25 = $result;
			$pos_25 = $this->pos;
			$_24 = \null;
			do {
				$_22 = \null;
				do {
					$res_16 = $result;
					$pos_16 = $this->pos;
					$_19 = \null;
					do {
						while (\true) {
							$res_17 = $result;
							$pos_17 = $this->pos;
							if (($subres = $this->rx('/\\\\{2}/')) !== \false) { $result["text"] .= $subres; }
							else {
								$result = $res_17;
								$this->setPos($pos_17);
								unset($res_17, $pos_17);
								break;
							}
						}
						if (($subres = $this->rx('/\\\\\'/')) !== \false) { $result["text"] .= $subres; }
						else { $_19 = \false; break; }
						$_19 = \true; break;
					}
					while(\false);
					if($_19 === \true) { $_22 = \true; break; }
					$result = $res_16;
					$this->setPos($pos_16);
					if (($subres = $this->rx('/[^\']/')) !== \false) {
						$result["text"] .= $subres;
						$_22 = \true; break;
					}
					$result = $res_16;
					$this->setPos($pos_16);
					$_22 = \false; break;
				}
				while(\false);
				if($_22 === \false) { $_24 = \false; break; }
				$_24 = \true; break;
			}
			while(\false);
			if($_24 === \false) {
				$result = $res_25;
				$this->setPos($pos_25);
				unset($res_25, $pos_25);
				break;
			}
		}
		if (($subres = $this->literal(''.$this->expression($result, $stack, 'q').'')) !== \false) { $result["text"] .= $subres; }
		else { $_27 = \false; break; }
		$_27 = \true; break;
	}
	while(\false);
	if($_27 === \true) { return $this->finalise($result); }
	if($_27 === \false) { return \false; }
}


/* DoubleQuoted: q:/"/ ( /\\{2}/ * /\\"/ | /[^"]/ ) * '$q' */
protected $match_DoubleQuoted_typestack = ['DoubleQuoted'];
function match_DoubleQuoted($stack = []) {
	$matchrule = 'DoubleQuoted';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_41 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "q");
		if (($subres = $this->rx('/"/')) !== \false) {
			$result["text"] .= $subres;
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'q');
		}
		else {
			$result = \array_pop($stack);
			$_41 = \false; break;
		}
		while (\true) {
			$res_39 = $result;
			$pos_39 = $this->pos;
			$_38 = \null;
			do {
				$_36 = \null;
				do {
					$res_30 = $result;
					$pos_30 = $this->pos;
					$_33 = \null;
					do {
						while (\true) {
							$res_31 = $result;
							$pos_31 = $this->pos;
							if (($subres = $this->rx('/\\\\{2}/')) !== \false) { $result["text"] .= $subres; }
							else {
								$result = $res_31;
								$this->setPos($pos_31);
								unset($res_31, $pos_31);
								break;
							}
						}
						if (($subres = $this->rx('/\\\\"/')) !== \false) { $result["text"] .= $subres; }
						else { $_33 = \false; break; }
						$_33 = \true; break;
					}
					while(\false);
					if($_33 === \true) { $_36 = \true; break; }
					$result = $res_30;
					$this->setPos($pos_30);
					if (($subres = $this->rx('/[^"]/')) !== \false) {
						$result["text"] .= $subres;
						$_36 = \true; break;
					}
					$result = $res_30;
					$this->setPos($pos_30);
					$_36 = \false; break;
				}
				while(\false);
				if($_36 === \false) { $_38 = \false; break; }
				$_38 = \true; break;
			}
			while(\false);
			if($_38 === \false) {
				$result = $res_39;
				$this->setPos($pos_39);
				unset($res_39, $pos_39);
				break;
			}
		}
		if (($subres = $this->literal(''.$this->expression($result, $stack, 'q').'')) !== \false) { $result["text"] .= $subres; }
		else { $_41 = \false; break; }
		$_41 = \true; break;
	}
	while(\false);
	if($_41 === \true) { return $this->finalise($result); }
	if($_41 === \false) { return \false; }
}


/* String: SingleQuoted | DoubleQuoted */
protected $match_String_typestack = ['String'];
function match_String($stack = []) {
	$matchrule = 'String';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_46 = \null;
	do {
		$res_43 = $result;
		$pos_43 = $this->pos;
		$key = 'match_'.'SingleQuoted'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_46 = \true; break;
		}
		$result = $res_43;
		$this->setPos($pos_43);
		$key = 'match_'.'DoubleQuoted'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_46 = \true; break;
		}
		$result = $res_43;
		$this->setPos($pos_43);
		$_46 = \false; break;
	}
	while(\false);
	if($_46 === \true) { return $this->finalise($result); }
	if($_46 === \false) { return \false; }
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
	$_50 = \null;
	do {
		if (($subres = $this->literal('0x')) !== \false) { $result["text"] .= $subres; }
		else { $_50 = \false; break; }
		if (($subres = $this->rx('/[0-9A-Fa-f]+/')) !== \false) { $result["text"] .= $subres; }
		else { $_50 = \false; break; }
		$_50 = \true; break;
	}
	while(\false);
	if($_50 === \true) { return $this->finalise($result); }
	if($_50 === \false) { return \false; }
}


/* Binary: '0b' /[01]+/ */
protected $match_Binary_typestack = ['Binary'];
function match_Binary($stack = []) {
	$matchrule = 'Binary';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_54 = \null;
	do {
		if (($subres = $this->literal('0b')) !== \false) { $result["text"] .= $subres; }
		else { $_54 = \false; break; }
		if (($subres = $this->rx('/[01]+/')) !== \false) { $result["text"] .= $subres; }
		else { $_54 = \false; break; }
		$_54 = \true; break;
	}
	while(\false);
	if($_54 === \true) { return $this->finalise($result); }
	if($_54 === \false) { return \false; }
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
	$_69 = \null;
	do {
		$res_58 = $result;
		$pos_58 = $this->pos;
		$key = 'match_'.'Hex'; $pos = $this->pos;
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
			$key = 'match_'.'Binary'; $pos = $this->pos;
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
				$key = 'match_'.'Float'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_65 = \true; break;
				}
				$result = $res_62;
				$this->setPos($pos_62);
				$key = 'match_'.'Decimal'; $pos = $this->pos;
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
	$_78 = \null;
	do {
		$res_71 = $result;
		$pos_71 = $this->pos;
		if (($subres = $this->literal('true')) !== \false) {
			$result["text"] .= $subres;
			$_78 = \true; break;
		}
		$result = $res_71;
		$this->setPos($pos_71);
		$_76 = \null;
		do {
			$res_73 = $result;
			$pos_73 = $this->pos;
			if (($subres = $this->literal('false')) !== \false) {
				$result["text"] .= $subres;
				$_76 = \true; break;
			}
			$result = $res_73;
			$this->setPos($pos_73);
			if (($subres = $this->literal('null')) !== \false) {
				$result["text"] .= $subres;
				$_76 = \true; break;
			}
			$result = $res_73;
			$this->setPos($pos_73);
			$_76 = \false; break;
		}
		while(\false);
		if($_76 === \true) { $_78 = \true; break; }
		$result = $res_71;
		$this->setPos($pos_71);
		$_78 = \false; break;
	}
	while(\false);
	if($_78 === \true) { return $this->finalise($result); }
	if($_78 === \false) { return \false; }
}


/* RegExp: /(?<!\\\\)\/(?:[^\/]|\\\\\/)+\// /[imsxUXJ]/* */
protected $match_RegExp_typestack = ['RegExp'];
function match_RegExp($stack = []) {
	$matchrule = 'RegExp';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_82 = \null;
	do {
		if (($subres = $this->rx('/(?<!\\\\\\\\)\/(?:[^\/]|\\\\\\\\\/)+\//')) !== \false) { $result["text"] .= $subres; }
		else { $_82 = \false; break; }
		while (\true) {
			$res_81 = $result;
			$pos_81 = $this->pos;
			if (($subres = $this->rx('/[imsxUXJ]/')) !== \false) { $result["text"] .= $subres; }
			else {
				$result = $res_81;
				$this->setPos($pos_81);
				unset($res_81, $pos_81);
				break;
			}
		}
		$_82 = \true; break;
	}
	while(\false);
	if($_82 === \true) { return $this->finalise($result); }
	if($_82 === \false) { return \false; }
}


/* SimpleValue: Consts | RegExp | String | Number */
protected $match_SimpleValue_typestack = ['SimpleValue'];
function match_SimpleValue($stack = []) {
	$matchrule = 'SimpleValue';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_95 = \null;
	do {
		$res_84 = $result;
		$pos_84 = $this->pos;
		$key = 'match_'.'Consts'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_95 = \true; break;
		}
		$result = $res_84;
		$this->setPos($pos_84);
		$_93 = \null;
		do {
			$res_86 = $result;
			$pos_86 = $this->pos;
			$key = 'match_'.'RegExp'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres);
				$_93 = \true; break;
			}
			$result = $res_86;
			$this->setPos($pos_86);
			$_91 = \null;
			do {
				$res_88 = $result;
				$pos_88 = $this->pos;
				$key = 'match_'.'String'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_91 = \true; break;
				}
				$result = $res_88;
				$this->setPos($pos_88);
				$key = 'match_'.'Number'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_91 = \true; break;
				}
				$result = $res_88;
				$this->setPos($pos_88);
				$_91 = \false; break;
			}
			while(\false);
			if($_91 === \true) { $_93 = \true; break; }
			$result = $res_86;
			$this->setPos($pos_86);
			$_93 = \false; break;
		}
		while(\false);
		if($_93 === \true) { $_95 = \true; break; }
		$result = $res_84;
		$this->setPos($pos_84);
		$_95 = \false; break;
	}
	while(\false);
	if($_95 === \true) { return $this->finalise($result); }
	if($_95 === \false) { return \false; }
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
	$_132 = \null;
	do {
		$res_98 = $result;
		$pos_98 = $this->pos;
		$_101 = \null;
		do {
			$key = 'match_'.'JSON'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else { $_101 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$_101 = \true; break;
		}
		while(\false);
		if($_101 === \true) { $_132 = \true; break; }
		$result = $res_98;
		$this->setPos($pos_98);
		$_130 = \null;
		do {
			$res_103 = $result;
			$pos_103 = $this->pos;
			$_106 = \null;
			do {
				$key = 'match_'.'SimpleValue'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_106 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_106 = \true; break;
			}
			while(\false);
			if($_106 === \true) { $_130 = \true; break; }
			$result = $res_103;
			$this->setPos($pos_103);
			$_128 = \null;
			do {
				$res_108 = $result;
				$pos_108 = $this->pos;
				$_111 = \null;
				do {
					$key = 'match_'.'FunctionCall'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) { $this->store($result, $subres); }
					else { $_111 = \false; break; }
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					$_111 = \true; break;
				}
				while(\false);
				if($_111 === \true) { $_128 = \true; break; }
				$result = $res_108;
				$this->setPos($pos_108);
				$_126 = \null;
				do {
					$res_113 = $result;
					$pos_113 = $this->pos;
					$_116 = \null;
					do {
						$key = 'match_'.'VariableReference'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) { $this->store($result, $subres); }
						else { $_116 = \false; break; }
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						$_116 = \true; break;
					}
					while(\false);
					if($_116 === \true) { $_126 = \true; break; }
					$result = $res_113;
					$this->setPos($pos_113);
					$_124 = \null;
					do {
						if (\substr($this->string, $this->pos, 1) === '(') {
							$this->addPos(1);
							$result["text"] .= '(';
						}
						else { $_124 = \false; break; }
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						$key = 'match_'.'Expr'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) { $this->store($result, $subres); }
						else { $_124 = \false; break; }
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						if (\substr($this->string, $this->pos, 1) === ')') {
							$this->addPos(1);
							$result["text"] .= ')';
						}
						else { $_124 = \false; break; }
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						$_124 = \true; break;
					}
					while(\false);
					if($_124 === \true) { $_126 = \true; break; }
					$result = $res_113;
					$this->setPos($pos_113);
					$_126 = \false; break;
				}
				while(\false);
				if($_126 === \true) { $_128 = \true; break; }
				$result = $res_108;
				$this->setPos($pos_108);
				$_128 = \false; break;
			}
			while(\false);
			if($_128 === \true) { $_130 = \true; break; }
			$result = $res_103;
			$this->setPos($pos_103);
			$_130 = \false; break;
		}
		while(\false);
		if($_130 === \true) { $_132 = \true; break; }
		$result = $res_98;
		$this->setPos($pos_98);
		$_132 = \false; break;
	}
	while(\false);
	if($_132 === \true) { return $this->finalise($result); }
	if($_132 === \false) { return \false; }
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
	$_147 = \null;
	do {
		$key = 'match_'.'Name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_147 = \false; break; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_147 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_143 = $result;
			$pos_143 = $this->pos;
			$_142 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'Expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_142 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$res_140 = $result;
				$pos_140 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else {
					$result = $res_140;
					$this->setPos($pos_140);
					unset($res_140, $pos_140);
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_142 = \true; break;
			}
			while(\false);
			if($_142 === \false) {
				$result = $res_143;
				$this->setPos($pos_143);
				unset($res_143, $pos_143);
				break;
			}
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_147 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_147 = \true; break;
	}
	while(\false);
	if($_147 === \true) { return $this->finalise($result); }
	if($_147 === \false) { return \false; }
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
	$_160 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "op");
		$_155 = \null;
		do {
			$_153 = \null;
			do {
				$res_150 = $result;
				$pos_150 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === '^') {
					$this->addPos(1);
					$result["text"] .= '^';
					$_153 = \true; break;
				}
				$result = $res_150;
				$this->setPos($pos_150);
				if (($subres = $this->literal('**')) !== \false) {
					$result["text"] .= $subres;
					$_153 = \true; break;
				}
				$result = $res_150;
				$this->setPos($pos_150);
				$_153 = \false; break;
			}
			while(\false);
			if($_153 === \false) { $_155 = \false; break; }
			$_155 = \true; break;
		}
		while(\false);
		if($_155 === \true) {
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'op');
		}
		if($_155 === \false) {
			$result = \array_pop($stack);
			$_160 = \false; break;
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Value'; $pos = $this->pos;
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


/* Power: Value > PowerOp * */
protected $match_Power_typestack = ['Power'];
function match_Power($stack = []) {
	$matchrule = 'Power';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_165 = \null;
	do {
		$key = 'match_'.'Value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_165 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_164 = $result;
			$pos_164 = $this->pos;
			$key = 'match_'.'PowerOp'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else {
				$result = $res_164;
				$this->setPos($pos_164);
				unset($res_164, $pos_164);
				break;
			}
		}
		$_165 = \true; break;
	}
	while(\false);
	if($_165 === \true) { return $this->finalise($result); }
	if($_165 === \false) { return \false; }
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
	$_171 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_171 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Power'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_171 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_171 = \true; break;
	}
	while(\false);
	if($_171 === \true) { return $this->finalise($result); }
	if($_171 === \false) { return \false; }
}


/* UnaryPlus: '+' > operand:Power > */
protected $match_UnaryPlus_typestack = ['UnaryPlus'];
function match_UnaryPlus($stack = []) {
	$matchrule = 'UnaryPlus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_177 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_177 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Power'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_177 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_177 = \true; break;
	}
	while(\false);
	if($_177 === \true) { return $this->finalise($result); }
	if($_177 === \false) { return \false; }
}


/* Negation: '!' > operand:Power > */
protected $match_Negation_typestack = ['Negation'];
function match_Negation($stack = []) {
	$matchrule = 'Negation';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_183 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '!') {
			$this->addPos(1);
			$result["text"] .= '!';
		}
		else { $_183 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Power'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_183 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_183 = \true; break;
	}
	while(\false);
	if($_183 === \true) { return $this->finalise($result); }
	if($_183 === \false) { return \false; }
}


/* Unary: ( Negation | UnaryPlus | UnaryMinus | Power | Property ) */
protected $match_Unary_typestack = ['Unary'];
function match_Unary($stack = []) {
	$matchrule = 'Unary';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_202 = \null;
	do {
		$_200 = \null;
		do {
			$res_185 = $result;
			$pos_185 = $this->pos;
			$key = 'match_'.'Negation'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres);
				$_200 = \true; break;
			}
			$result = $res_185;
			$this->setPos($pos_185);
			$_198 = \null;
			do {
				$res_187 = $result;
				$pos_187 = $this->pos;
				$key = 'match_'.'UnaryPlus'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_198 = \true; break;
				}
				$result = $res_187;
				$this->setPos($pos_187);
				$_196 = \null;
				do {
					$res_189 = $result;
					$pos_189 = $this->pos;
					$key = 'match_'.'UnaryMinus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_196 = \true; break;
					}
					$result = $res_189;
					$this->setPos($pos_189);
					$_194 = \null;
					do {
						$res_191 = $result;
						$pos_191 = $this->pos;
						$key = 'match_'.'Power'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_194 = \true; break;
						}
						$result = $res_191;
						$this->setPos($pos_191);
						$key = 'match_'.'Property'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_194 = \true; break;
						}
						$result = $res_191;
						$this->setPos($pos_191);
						$_194 = \false; break;
					}
					while(\false);
					if($_194 === \true) { $_196 = \true; break; }
					$result = $res_189;
					$this->setPos($pos_189);
					$_196 = \false; break;
				}
				while(\false);
				if($_196 === \true) { $_198 = \true; break; }
				$result = $res_187;
				$this->setPos($pos_187);
				$_198 = \false; break;
			}
			while(\false);
			if($_198 === \true) { $_200 = \true; break; }
			$result = $res_185;
			$this->setPos($pos_185);
			$_200 = \false; break;
		}
		while(\false);
		if($_200 === \false) { $_202 = \false; break; }
		$_202 = \true; break;
	}
	while(\false);
	if($_202 === \true) { return $this->finalise($result); }
	if($_202 === \false) { return \false; }
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
	$_208 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '*') {
			$this->addPos(1);
			$result["text"] .= '*';
		}
		else { $_208 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_208 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_208 = \true; break;
	}
	while(\false);
	if($_208 === \true) { return $this->finalise($result); }
	if($_208 === \false) { return \false; }
}


/* Div: '/' > operand:Unary > */
protected $match_Div_typestack = ['Div'];
function match_Div($stack = []) {
	$matchrule = 'Div';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_214 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '/') {
			$this->addPos(1);
			$result["text"] .= '/';
		}
		else { $_214 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_214 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_214 = \true; break;
	}
	while(\false);
	if($_214 === \true) { return $this->finalise($result); }
	if($_214 === \false) { return \false; }
}


/* Mod: '%' > operand:Unary > */
protected $match_Mod_typestack = ['Mod'];
function match_Mod($stack = []) {
	$matchrule = 'Mod';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_220 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '%') {
			$this->addPos(1);
			$result["text"] .= '%';
		}
		else { $_220 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_220 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_220 = \true; break;
	}
	while(\false);
	if($_220 === \true) { return $this->finalise($result); }
	if($_220 === \false) { return \false; }
}


/* ImplicitTimes: operand:Power > */
protected $match_ImplicitTimes_typestack = ['ImplicitTimes'];
function match_ImplicitTimes($stack = []) {
	$matchrule = 'ImplicitTimes';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_224 = \null;
	do {
		$key = 'match_'.'Power'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_224 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_224 = \true; break;
	}
	while(\false);
	if($_224 === \true) { return $this->finalise($result); }
	if($_224 === \false) { return \false; }
}


/* Property: '[' > operand:Expr > ']' > */
protected $match_Property_typestack = ['Property'];
function match_Property($stack = []) {
	$matchrule = 'Property';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_232 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '[') {
			$this->addPos(1);
			$result["text"] .= '[';
		}
		else { $_232 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_232 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ']') {
			$this->addPos(1);
			$result["text"] .= ']';
		}
		else { $_232 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_232 = \true; break;
	}
	while(\false);
	if($_232 === \true) { return $this->finalise($result); }
	if($_232 === \false) { return \false; }
}


/* Product: Unary > ( Times | Div | Mod | Property | ImplicitTimes ) * */
protected $match_Product_typestack = ['Product'];
function match_Product($stack = []) {
	$matchrule = 'Product';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_255 = \null;
	do {
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_255 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_254 = $result;
			$pos_254 = $this->pos;
			$_253 = \null;
			do {
				$_251 = \null;
				do {
					$res_236 = $result;
					$pos_236 = $this->pos;
					$key = 'match_'.'Times'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_251 = \true; break;
					}
					$result = $res_236;
					$this->setPos($pos_236);
					$_249 = \null;
					do {
						$res_238 = $result;
						$pos_238 = $this->pos;
						$key = 'match_'.'Div'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_249 = \true; break;
						}
						$result = $res_238;
						$this->setPos($pos_238);
						$_247 = \null;
						do {
							$res_240 = $result;
							$pos_240 = $this->pos;
							$key = 'match_'.'Mod'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres);
								$_247 = \true; break;
							}
							$result = $res_240;
							$this->setPos($pos_240);
							$_245 = \null;
							do {
								$res_242 = $result;
								$pos_242 = $this->pos;
								$key = 'match_'.'Property'; $pos = $this->pos;
								$subres = $this->packhas($key, $pos)
									? $this->packread($key, $pos)
									: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
								if ($subres !== \false) {
									$this->store($result, $subres);
									$_245 = \true; break;
								}
								$result = $res_242;
								$this->setPos($pos_242);
								$key = 'match_'.'ImplicitTimes'; $pos = $this->pos;
								$subres = $this->packhas($key, $pos)
									? $this->packread($key, $pos)
									: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
								if ($subres !== \false) {
									$this->store($result, $subres);
									$_245 = \true; break;
								}
								$result = $res_242;
								$this->setPos($pos_242);
								$_245 = \false; break;
							}
							while(\false);
							if($_245 === \true) { $_247 = \true; break; }
							$result = $res_240;
							$this->setPos($pos_240);
							$_247 = \false; break;
						}
						while(\false);
						if($_247 === \true) { $_249 = \true; break; }
						$result = $res_238;
						$this->setPos($pos_238);
						$_249 = \false; break;
					}
					while(\false);
					if($_249 === \true) { $_251 = \true; break; }
					$result = $res_236;
					$this->setPos($pos_236);
					$_251 = \false; break;
				}
				while(\false);
				if($_251 === \false) { $_253 = \false; break; }
				$_253 = \true; break;
			}
			while(\false);
			if($_253 === \false) {
				$result = $res_254;
				$this->setPos($pos_254);
				unset($res_254, $pos_254);
				break;
			}
		}
		$_255 = \true; break;
	}
	while(\false);
	if($_255 === \true) { return $this->finalise($result); }
	if($_255 === \false) { return \false; }
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
	$_261 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_261 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_261 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_261 = \true; break;
	}
	while(\false);
	if($_261 === \true) { return $this->finalise($result); }
	if($_261 === \false) { return \false; }
}


/* Minus: '-' > operand:Product > */
protected $match_Minus_typestack = ['Minus'];
function match_Minus($stack = []) {
	$matchrule = 'Minus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_267 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_267 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
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


/* Sum: Product > ( Plus | Minus ) * */
protected $match_Sum_typestack = ['Sum'];
function match_Sum($stack = []) {
	$matchrule = 'Sum';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_278 = \null;
	do {
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_278 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_277 = $result;
			$pos_277 = $this->pos;
			$_276 = \null;
			do {
				$_274 = \null;
				do {
					$res_271 = $result;
					$pos_271 = $this->pos;
					$key = 'match_'.'Plus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_274 = \true; break;
					}
					$result = $res_271;
					$this->setPos($pos_271);
					$key = 'match_'.'Minus'; $pos = $this->pos;
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
				if($_274 === \false) { $_276 = \false; break; }
				$_276 = \true; break;
			}
			while(\false);
			if($_276 === \false) {
				$result = $res_277;
				$this->setPos($pos_277);
				unset($res_277, $pos_277);
				break;
			}
		}
		$_278 = \true; break;
	}
	while(\false);
	if($_278 === \true) { return $this->finalise($result); }
	if($_278 === \false) { return \false; }
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
	$_285 = \null;
	do {
		$key = 'match_'.'Variable'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_285 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '=') {
			$this->addPos(1);
			$result["text"] .= '=';
		}
		else { $_285 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_285 = \false; break; }
		$_285 = \true; break;
	}
	while(\false);
	if($_285 === \true) { return $this->finalise($result); }
	if($_285 === \false) { return \false; }
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
	$_304 = \null;
	do {
		$key = 'match_'.'Name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_304 = \false; break; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_304 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_297 = $result;
			$pos_297 = $this->pos;
			$_296 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'Variable'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_296 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$res_294 = $result;
				$pos_294 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else {
					$result = $res_294;
					$this->setPos($pos_294);
					unset($res_294, $pos_294);
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
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
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_304 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '=') {
			$this->addPos(1);
			$result["text"] .= '=';
		}
		else { $_304 = \false; break; }
		$res_301 = $result;
		$pos_301 = $this->pos;
		if (($subres = $this->rx('/[=~]/')) !== \false) {
			$result["text"] .= $subres;
			$result = $res_301;
			$this->setPos($pos_301);
			$_304 = \false; break;
		}
		else {
			$result = $res_301;
			$this->setPos($pos_301);
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'FunctionBody'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_304 = \false; break; }
		$_304 = \true; break;
	}
	while(\false);
	if($_304 === \true) { return $this->finalise($result); }
	if($_304 === \false) { return \false; }
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
	$_310 = \null;
	do {
		if (($subres = $this->literal('<<')) !== \false) { $result["text"] .= $subres; }
		else { $_310 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Sum'; $pos = $this->pos;
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


/* ShiftRight: '>>' > operand:Sum > */
protected $match_ShiftRight_typestack = ['ShiftRight'];
function match_ShiftRight($stack = []) {
	$matchrule = 'ShiftRight';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_316 = \null;
	do {
		if (($subres = $this->literal('>>')) !== \false) { $result["text"] .= $subres; }
		else { $_316 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Sum'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_316 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_316 = \true; break;
	}
	while(\false);
	if($_316 === \true) { return $this->finalise($result); }
	if($_316 === \false) { return \false; }
}


/* BitShift: Sum > (ShiftRight | ShiftLeft) * */
protected $match_BitShift_typestack = ['BitShift'];
function match_BitShift($stack = []) {
	$matchrule = 'BitShift';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_327 = \null;
	do {
		$key = 'match_'.'Sum'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_327 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_326 = $result;
			$pos_326 = $this->pos;
			$_325 = \null;
			do {
				$_323 = \null;
				do {
					$res_320 = $result;
					$pos_320 = $this->pos;
					$key = 'match_'.'ShiftRight'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_323 = \true; break;
					}
					$result = $res_320;
					$this->setPos($pos_320);
					$key = 'match_'.'ShiftLeft'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_323 = \true; break;
					}
					$result = $res_320;
					$this->setPos($pos_320);
					$_323 = \false; break;
				}
				while(\false);
				if($_323 === \false) { $_325 = \false; break; }
				$_325 = \true; break;
			}
			while(\false);
			if($_325 === \false) {
				$result = $res_326;
				$this->setPos($pos_326);
				unset($res_326, $pos_326);
				break;
			}
		}
		$_327 = \true; break;
	}
	while(\false);
	if($_327 === \true) { return $this->finalise($result); }
	if($_327 === \false) { return \false; }
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
	$_333 = \null;
	do {
		if (($subres = $this->literal('===')) !== \false) { $result["text"] .= $subres; }
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


/* StrictNotEqual: '!==' > operand:BitShift > */
protected $match_StrictNotEqual_typestack = ['StrictNotEqual'];
function match_StrictNotEqual($stack = []) {
	$matchrule = 'StrictNotEqual';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_339 = \null;
	do {
		if (($subres = $this->literal('!==')) !== \false) { $result["text"] .= $subres; }
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


/* Equal: '==' > operand:BitShift > */
protected $match_Equal_typestack = ['Equal'];
function match_Equal($stack = []) {
	$matchrule = 'Equal';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_345 = \null;
	do {
		if (($subres = $this->literal('==')) !== \false) { $result["text"] .= $subres; }
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


/* Match: '=~' > operand:BitShift > */
protected $match_Match_typestack = ['Match'];
function match_Match($stack = []) {
	$matchrule = 'Match';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_351 = \null;
	do {
		if (($subres = $this->literal('=~')) !== \false) { $result["text"] .= $subres; }
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


/* NotEqual: '!=' > operand:BitShift > */
protected $match_NotEqual_typestack = ['NotEqual'];
function match_NotEqual($stack = []) {
	$matchrule = 'NotEqual';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_357 = \null;
	do {
		if (($subres = $this->literal('!=')) !== \false) { $result["text"] .= $subres; }
		else { $_357 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_357 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_357 = \true; break;
	}
	while(\false);
	if($_357 === \true) { return $this->finalise($result); }
	if($_357 === \false) { return \false; }
}


/* GreaterEqualThan: '>=' > operand:BitShift > */
protected $match_GreaterEqualThan_typestack = ['GreaterEqualThan'];
function match_GreaterEqualThan($stack = []) {
	$matchrule = 'GreaterEqualThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_363 = \null;
	do {
		if (($subres = $this->literal('>=')) !== \false) { $result["text"] .= $subres; }
		else { $_363 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_363 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_363 = \true; break;
	}
	while(\false);
	if($_363 === \true) { return $this->finalise($result); }
	if($_363 === \false) { return \false; }
}


/* LessEqualThan: '<=' > operand:BitShift > */
protected $match_LessEqualThan_typestack = ['LessEqualThan'];
function match_LessEqualThan($stack = []) {
	$matchrule = 'LessEqualThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_369 = \null;
	do {
		if (($subres = $this->literal('<=')) !== \false) { $result["text"] .= $subres; }
		else { $_369 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_369 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_369 = \true; break;
	}
	while(\false);
	if($_369 === \true) { return $this->finalise($result); }
	if($_369 === \false) { return \false; }
}


/* GreaterThan: '>' > operand:BitShift > */
protected $match_GreaterThan_typestack = ['GreaterThan'];
function match_GreaterThan($stack = []) {
	$matchrule = 'GreaterThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_375 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '>') {
			$this->addPos(1);
			$result["text"] .= '>';
		}
		else { $_375 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_375 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_375 = \true; break;
	}
	while(\false);
	if($_375 === \true) { return $this->finalise($result); }
	if($_375 === \false) { return \false; }
}


/* LessThan: '<' > operand:BitShift > */
protected $match_LessThan_typestack = ['LessThan'];
function match_LessThan($stack = []) {
	$matchrule = 'LessThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_381 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '<') {
			$this->addPos(1);
			$result["text"] .= '<';
		}
		else { $_381 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_381 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_381 = \true; break;
	}
	while(\false);
	if($_381 === \true) { return $this->finalise($result); }
	if($_381 === \false) { return \false; }
}


/* Compare: BitShift > (StrictEqual | Equal | Match | StrictNotEqual | NotEqual | GreaterEqualThan | GreaterThan | LessEqualThan | LessThan ) * */
protected $match_Compare_typestack = ['Compare'];
function match_Compare($stack = []) {
	$matchrule = 'Compare';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_420 = \null;
	do {
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_420 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_419 = $result;
			$pos_419 = $this->pos;
			$_418 = \null;
			do {
				$_416 = \null;
				do {
					$res_385 = $result;
					$pos_385 = $this->pos;
					$key = 'match_'.'StrictEqual'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_416 = \true; break;
					}
					$result = $res_385;
					$this->setPos($pos_385);
					$_414 = \null;
					do {
						$res_387 = $result;
						$pos_387 = $this->pos;
						$key = 'match_'.'Equal'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_414 = \true; break;
						}
						$result = $res_387;
						$this->setPos($pos_387);
						$_412 = \null;
						do {
							$res_389 = $result;
							$pos_389 = $this->pos;
							$key = 'match_'.'Match'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres);
								$_412 = \true; break;
							}
							$result = $res_389;
							$this->setPos($pos_389);
							$_410 = \null;
							do {
								$res_391 = $result;
								$pos_391 = $this->pos;
								$key = 'match_'.'StrictNotEqual'; $pos = $this->pos;
								$subres = $this->packhas($key, $pos)
									? $this->packread($key, $pos)
									: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
								if ($subres !== \false) {
									$this->store($result, $subres);
									$_410 = \true; break;
								}
								$result = $res_391;
								$this->setPos($pos_391);
								$_408 = \null;
								do {
									$res_393 = $result;
									$pos_393 = $this->pos;
									$key = 'match_'.'NotEqual'; $pos = $this->pos;
									$subres = $this->packhas($key, $pos)
										? $this->packread($key, $pos)
										: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
									if ($subres !== \false) {
										$this->store($result, $subres);
										$_408 = \true; break;
									}
									$result = $res_393;
									$this->setPos($pos_393);
									$_406 = \null;
									do {
										$res_395 = $result;
										$pos_395 = $this->pos;
										$key = 'match_'.'GreaterEqualThan'; $pos = $this->pos;
										$subres = $this->packhas($key, $pos)
											? $this->packread($key, $pos)
											: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
										if ($subres !== \false) {
											$this->store($result, $subres);
											$_406 = \true; break;
										}
										$result = $res_395;
										$this->setPos($pos_395);
										$_404 = \null;
										do {
											$res_397 = $result;
											$pos_397 = $this->pos;
											$key = 'match_'.'GreaterThan'; $pos = $this->pos;
											$subres = $this->packhas($key, $pos)
												? $this->packread($key, $pos)
												: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
											if ($subres !== \false) {
												$this->store($result, $subres);
												$_404 = \true; break;
											}
											$result = $res_397;
											$this->setPos($pos_397);
											$_402 = \null;
											do {
												$res_399 = $result;
												$pos_399 = $this->pos;
												$key = 'match_'.'LessEqualThan'; $pos = $this->pos;
												$subres = $this->packhas($key, $pos)
													? $this->packread($key, $pos)
													: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
												if ($subres !== \false) {
													$this->store($result, $subres);
													$_402 = \true; break;
												}
												$result = $res_399;
												$this->setPos($pos_399);
												$key = 'match_'.'LessThan'; $pos = $this->pos;
												$subres = $this->packhas($key, $pos)
													? $this->packread($key, $pos)
													: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
												if ($subres !== \false) {
													$this->store($result, $subres);
													$_402 = \true; break;
												}
												$result = $res_399;
												$this->setPos($pos_399);
												$_402 = \false; break;
											}
											while(\false);
											if($_402 === \true) { $_404 = \true; break; }
											$result = $res_397;
											$this->setPos($pos_397);
											$_404 = \false; break;
										}
										while(\false);
										if($_404 === \true) { $_406 = \true; break; }
										$result = $res_395;
										$this->setPos($pos_395);
										$_406 = \false; break;
									}
									while(\false);
									if($_406 === \true) { $_408 = \true; break; }
									$result = $res_393;
									$this->setPos($pos_393);
									$_408 = \false; break;
								}
								while(\false);
								if($_408 === \true) { $_410 = \true; break; }
								$result = $res_391;
								$this->setPos($pos_391);
								$_410 = \false; break;
							}
							while(\false);
							if($_410 === \true) { $_412 = \true; break; }
							$result = $res_389;
							$this->setPos($pos_389);
							$_412 = \false; break;
						}
						while(\false);
						if($_412 === \true) { $_414 = \true; break; }
						$result = $res_387;
						$this->setPos($pos_387);
						$_414 = \false; break;
					}
					while(\false);
					if($_414 === \true) { $_416 = \true; break; }
					$result = $res_385;
					$this->setPos($pos_385);
					$_416 = \false; break;
				}
				while(\false);
				if($_416 === \false) { $_418 = \false; break; }
				$_418 = \true; break;
			}
			while(\false);
			if($_418 === \false) {
				$result = $res_419;
				$this->setPos($pos_419);
				unset($res_419, $pos_419);
				break;
			}
		}
		$_420 = \true; break;
	}
	while(\false);
	if($_420 === \true) { return $this->finalise($result); }
	if($_420 === \false) { return \false; }
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
            print_r($re);
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
	$_426 = \null;
	do {
		if (($subres = $this->literal('&&')) !== \false) { $result["text"] .= $subres; }
		else { $_426 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_426 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_426 = \true; break;
	}
	while(\false);
	if($_426 === \true) { return $this->finalise($result); }
	if($_426 === \false) { return \false; }
}


/* Or: '||' > operand:Compare > */
protected $match_Or_typestack = ['Or'];
function match_Or($stack = []) {
	$matchrule = 'Or';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_432 = \null;
	do {
		if (($subres = $this->literal('||')) !== \false) { $result["text"] .= $subres; }
		else { $_432 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_432 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_432 = \true; break;
	}
	while(\false);
	if($_432 === \true) { return $this->finalise($result); }
	if($_432 === \false) { return \false; }
}


/* Boolean: Compare > (And | Or ) * */
protected $match_Boolean_typestack = ['Boolean'];
function match_Boolean($stack = []) {
	$matchrule = 'Boolean';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_443 = \null;
	do {
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_443 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_442 = $result;
			$pos_442 = $this->pos;
			$_441 = \null;
			do {
				$_439 = \null;
				do {
					$res_436 = $result;
					$pos_436 = $this->pos;
					$key = 'match_'.'And'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_439 = \true; break;
					}
					$result = $res_436;
					$this->setPos($pos_436);
					$key = 'match_'.'Or'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_439 = \true; break;
					}
					$result = $res_436;
					$this->setPos($pos_436);
					$_439 = \false; break;
				}
				while(\false);
				if($_439 === \false) { $_441 = \false; break; }
				$_441 = \true; break;
			}
			while(\false);
			if($_441 === \false) {
				$result = $res_442;
				$this->setPos($pos_442);
				unset($res_442, $pos_442);
				break;
			}
		}
		$_443 = \true; break;
	}
	while(\false);
	if($_443 === \true) { return $this->finalise($result); }
	if($_443 === \false) { return \false; }
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
	$_458 = \null;
	do {
		$_455 = \null;
		do {
			$_453 = \null;
			do {
				$res_446 = $result;
				$pos_446 = $this->pos;
				$key = 'match_'.'VariableAssignment'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_453 = \true; break;
				}
				$result = $res_446;
				$this->setPos($pos_446);
				$_451 = \null;
				do {
					$res_448 = $result;
					$pos_448 = $this->pos;
					$key = 'match_'.'FunctionAssignment'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_451 = \true; break;
					}
					$result = $res_448;
					$this->setPos($pos_448);
					$key = 'match_'.'Expr'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_451 = \true; break;
					}
					$result = $res_448;
					$this->setPos($pos_448);
					$_451 = \false; break;
				}
				while(\false);
				if($_451 === \true) { $_453 = \true; break; }
				$result = $res_446;
				$this->setPos($pos_446);
				$_453 = \false; break;
			}
			while(\false);
			if($_453 === \false) { $_455 = \false; break; }
			$_455 = \true; break;
		}
		while(\false);
		if($_455 === \false) { $_458 = \false; break; }
		$res_457 = $result;
		$pos_457 = $this->pos;
		if (\substr($this->string, $this->pos, 1) === ';') {
			$this->addPos(1);
			$result["text"] .= ';';
		}
		else {
			$result = $res_457;
			$this->setPos($pos_457);
			unset($res_457, $pos_457);
		}
		$_458 = \true; break;
	}
	while(\false);
	if($_458 === \true) { return $this->finalise($result); }
	if($_458 === \false) { return \false; }
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

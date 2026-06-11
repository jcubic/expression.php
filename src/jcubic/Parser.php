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
           $expr = new jcubic\Expression();
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
    private function to_array($value) {
        if ($this->is_array($value)) {
            return $value['value'];
        }
        return [$value['value']];
    }
    private function add($left, $right) {
        if ($this->is_array($left) || $this->is_array($right)) {
            return $this->with_type(
                array_merge($this->to_array($left), $this->to_array($right)),
                'array'
            );
        }
        if ($this->is_string($right)) {
            return $this->with_type($left['value'] . $right['value']);
        }
        $this->validate_number('+', $right);
        $this->validate_number('+', $left);
        return $this->with_type($left['value'] + $right['value']);
    }
    private function subtract($left, $right) {
        if ($this->is_array($left) || $this->is_array($right)) {
            $list = $this->to_array($right);
            $result = [];
            foreach ($this->to_array($left) as $item) {
                if (!in_array($item, $list)) {
                    $result[] = $item;
                }
            }
            return $this->with_type($result, 'array');
        }
        $this->validate_number('-', $right);
        $this->validate_number('-', $left);
        return $this->with_type($left['value'] - $right['value']);
    }
    private function multiply($left, $right) {
        if ($this->is_array($left) || $this->is_array($right)) {
            if ($this->is_array($left) && $this->is_string($right)) {
                return $this->with_type(implode($right['value'], $left['value']));
            }
            if ($this->is_array($right) && $this->is_string($left)) {
                return $this->with_type(implode($left['value'], $right['value']));
            }
            $array = $this->is_array($left) ? $left : $right;
            $number = $this->is_array($left) ? $right : $left;
            $this->validate_number('*', $number);
            $result = [];
            for ($i = 0; $i < intval($number['value']); $i++) {
                $result = array_merge($result, $array['value']);
            }
            return $this->with_type($result, 'array');
        }
        if ($this->is_string($left) || $this->is_string($right)) {
            $string = $this->is_string($left) ? $left : $right;
            $number = $this->is_string($left) ? $right : $left;
            $this->validate_number('*', $number);
            return $this->with_type(str_repeat($string['value'], intval($number['value'])));
        }
        $this->validate_number('*', $left);
        $this->validate_number('*', $right);
        return $this->with_type($left['value'] * $right['value']);
    }
    private function intersect($left, $right) {
        if (!$this->is_array($left) && !$this->is_array($right)) {
            $this->validate_number('&', $left);
            $this->validate_number('&', $right);
            return $this->with_type(intval($left['value']) & intval($right['value']));
        }
        $list = $this->to_array($right);
        $result = [];
        foreach ($this->to_array($left) as $item) {
            if (in_array($item, $list) && !in_array($item, $result)) {
                $result[] = $item;
            }
        }
        return $this->with_type($result, 'array');
    }
    private function union($left, $right) {
        if (!$this->is_array($left) && !$this->is_array($right)) {
            $this->validate_number('|', $left);
            $this->validate_number('|', $right);
            return $this->with_type(intval($left['value']) | intval($right['value']));
        }
        $result = [];
        foreach (array_merge($this->to_array($left), $this->to_array($right)) as $item) {
            if (!in_array($item, $result)) {
                $result[] = $item;
            }
        }
        return $this->with_type($result, 'array');
    }
    private function append($left, $right) {
        if ($this->is_array($left)) {
            $array = $left['value'];
            $array[] = $right['value'];
            return $this->with_type($array, 'array');
        }
        if ($this->is_string($left)) {
            return $this->with_type($left['value'] . (string)$right['value']);
        }
        return $this->shift('<<', $left, $right, function($a, $b) {
            return $a << $b;
        });
    }
    private function spaceship($left, $right) {
        return $this->with_type($left['value'] <=> $right['value']);
    }
    private function member($left, $right) {
        if ($this->is_array($right)) {
            return $this->with_type(in_array($left['value'], $right['value']));
        }
        if ($this->is_string($right)) {
            return $this->with_type(str_contains($right['value'], (string)$left['value']));
        }
        return $this->with_type(in_array($left['value'], [$right['value']]));
    }

/* Name: !/in(?![A-Za-z_0-9])/ (/[A-Za-z_]/ /[A-Za-z_0-9]/* | '$' /[0-9A-Za-z_]+/) */
protected $match_Name_typestack = ['Name'];
function match_Name($stack = []) {
	$matchrule = 'Name';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_14 = \null;
	do {
		$res_0 = $result;
		$pos_0 = $this->pos;
		if (($subres = $this->rx('/in(?![A-Za-z_0-9])/')) !== \false) {
			$result["text"] .= $subres;
			$result = $res_0;
			$this->setPos($pos_0);
			$_14 = \false; break;
		}
		else {
			$result = $res_0;
			$this->setPos($pos_0);
		}
		$_12 = \null;
		do {
			$_10 = \null;
			do {
				$res_1 = $result;
				$pos_1 = $this->pos;
				$_4 = \null;
				do {
					if (($subres = $this->rx('/[A-Za-z_]/')) !== \false) { $result["text"] .= $subres; }
					else { $_4 = \false; break; }
					while (\true) {
						$res_3 = $result;
						$pos_3 = $this->pos;
						if (($subres = $this->rx('/[A-Za-z_0-9]/')) !== \false) { $result["text"] .= $subres; }
						else {
							$result = $res_3;
							$this->setPos($pos_3);
							unset($res_3, $pos_3);
							break;
						}
					}
					$_4 = \true; break;
				}
				while(\false);
				if($_4 === \true) { $_10 = \true; break; }
				$result = $res_1;
				$this->setPos($pos_1);
				$_8 = \null;
				do {
					if (\substr($this->string, $this->pos, 1) === '$') {
						$this->addPos(1);
						$result["text"] .= '$';
					}
					else { $_8 = \false; break; }
					if (($subres = $this->rx('/[0-9A-Za-z_]+/')) !== \false) { $result["text"] .= $subres; }
					else { $_8 = \false; break; }
					$_8 = \true; break;
				}
				while(\false);
				if($_8 === \true) { $_10 = \true; break; }
				$result = $res_1;
				$this->setPos($pos_1);
				$_10 = \false; break;
			}
			while(\false);
			if($_10 === \false) { $_12 = \false; break; }
			$_12 = \true; break;
		}
		while(\false);
		if($_12 === \false) { $_14 = \false; break; }
		$_14 = \true; break;
	}
	while(\false);
	if($_14 === \true) { return $this->finalise($result); }
	if($_14 === \false) { return \false; }
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
	$_23 = \null;
	do {
		$res_20 = $result;
		$pos_20 = $this->pos;
		$key = 'match_'.'SingleQuoted'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_23 = \true; break;
		}
		$result = $res_20;
		$this->setPos($pos_20);
		$key = 'match_'.'DoubleQuoted'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_23 = \true; break;
		}
		$result = $res_20;
		$this->setPos($pos_20);
		$_23 = \false; break;
	}
	while(\false);
	if($_23 === \true) { return $this->finalise($result); }
	if($_23 === \false) { return \false; }
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
	$_27 = \null;
	do {
		if (($subres = $this->literal('0x')) !== \false) { $result["text"] .= $subres; }
		else { $_27 = \false; break; }
		if (($subres = $this->rx('/[0-9A-Fa-f]+/')) !== \false) { $result["text"] .= $subres; }
		else { $_27 = \false; break; }
		$_27 = \true; break;
	}
	while(\false);
	if($_27 === \true) { return $this->finalise($result); }
	if($_27 === \false) { return \false; }
}


/* Binary: '0b' /[01]+/ */
protected $match_Binary_typestack = ['Binary'];
function match_Binary($stack = []) {
	$matchrule = 'Binary';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_31 = \null;
	do {
		if (($subres = $this->literal('0b')) !== \false) { $result["text"] .= $subres; }
		else { $_31 = \false; break; }
		if (($subres = $this->rx('/[01]+/')) !== \false) { $result["text"] .= $subres; }
		else { $_31 = \false; break; }
		$_31 = \true; break;
	}
	while(\false);
	if($_31 === \true) { return $this->finalise($result); }
	if($_31 === \false) { return \false; }
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


/* Float: /([0-9]+[.])?[0-9]+e[0-9]+|[0-9]+(?:\.[0-9]*)?|\.[0-9]+/ */
protected $match_Float_typestack = ['Float'];
function match_Float($stack = []) {
	$matchrule = 'Float';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/([0-9]+[.])?[0-9]+e[0-9]+|[0-9]+(?:\.[0-9]*)?|\.[0-9]+/')) !== \false) {
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
	$_46 = \null;
	do {
		$res_35 = $result;
		$pos_35 = $this->pos;
		$key = 'match_'.'Hex'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_46 = \true; break;
		}
		$result = $res_35;
		$this->setPos($pos_35);
		$_44 = \null;
		do {
			$res_37 = $result;
			$pos_37 = $this->pos;
			$key = 'match_'.'Binary'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres);
				$_44 = \true; break;
			}
			$result = $res_37;
			$this->setPos($pos_37);
			$_42 = \null;
			do {
				$res_39 = $result;
				$pos_39 = $this->pos;
				$key = 'match_'.'Float'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_42 = \true; break;
				}
				$result = $res_39;
				$this->setPos($pos_39);
				$key = 'match_'.'Decimal'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_42 = \true; break;
				}
				$result = $res_39;
				$this->setPos($pos_39);
				$_42 = \false; break;
			}
			while(\false);
			if($_42 === \true) { $_44 = \true; break; }
			$result = $res_37;
			$this->setPos($pos_37);
			$_44 = \false; break;
		}
		while(\false);
		if($_44 === \true) { $_46 = \true; break; }
		$result = $res_35;
		$this->setPos($pos_35);
		$_46 = \false; break;
	}
	while(\false);
	if($_46 === \true) { return $this->finalise($result); }
	if($_46 === \false) { return \false; }
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
	$_55 = \null;
	do {
		$res_48 = $result;
		$pos_48 = $this->pos;
		if (($subres = $this->literal('true')) !== \false) {
			$result["text"] .= $subres;
			$_55 = \true; break;
		}
		$result = $res_48;
		$this->setPos($pos_48);
		$_53 = \null;
		do {
			$res_50 = $result;
			$pos_50 = $this->pos;
			if (($subres = $this->literal('false')) !== \false) {
				$result["text"] .= $subres;
				$_53 = \true; break;
			}
			$result = $res_50;
			$this->setPos($pos_50);
			if (($subres = $this->literal('null')) !== \false) {
				$result["text"] .= $subres;
				$_53 = \true; break;
			}
			$result = $res_50;
			$this->setPos($pos_50);
			$_53 = \false; break;
		}
		while(\false);
		if($_53 === \true) { $_55 = \true; break; }
		$result = $res_48;
		$this->setPos($pos_48);
		$_55 = \false; break;
	}
	while(\false);
	if($_55 === \true) { return $this->finalise($result); }
	if($_55 === \false) { return \false; }
}


/* RegExp: /(?<!\\\\)\/(?:[^\/]|\\\\\/)+\// /[imsxUXJ]/* */
protected $match_RegExp_typestack = ['RegExp'];
function match_RegExp($stack = []) {
	$matchrule = 'RegExp';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_59 = \null;
	do {
		if (($subres = $this->rx('/(?<!\\\\\\\\)\/(?:[^\/]|\\\\\\\\\/)+\//')) !== \false) { $result["text"] .= $subres; }
		else { $_59 = \false; break; }
		while (\true) {
			$res_58 = $result;
			$pos_58 = $this->pos;
			if (($subres = $this->rx('/[imsxUXJ]/')) !== \false) { $result["text"] .= $subres; }
			else {
				$result = $res_58;
				$this->setPos($pos_58);
				unset($res_58, $pos_58);
				break;
			}
		}
		$_59 = \true; break;
	}
	while(\false);
	if($_59 === \true) { return $this->finalise($result); }
	if($_59 === \false) { return \false; }
}


/* SimpleValue: Consts | RegExp | String | Number */
protected $match_SimpleValue_typestack = ['SimpleValue'];
function match_SimpleValue($stack = []) {
	$matchrule = 'SimpleValue';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_72 = \null;
	do {
		$res_61 = $result;
		$pos_61 = $this->pos;
		$key = 'match_'.'Consts'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_72 = \true; break;
		}
		$result = $res_61;
		$this->setPos($pos_61);
		$_70 = \null;
		do {
			$res_63 = $result;
			$pos_63 = $this->pos;
			$key = 'match_'.'RegExp'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres);
				$_70 = \true; break;
			}
			$result = $res_63;
			$this->setPos($pos_63);
			$_68 = \null;
			do {
				$res_65 = $result;
				$pos_65 = $this->pos;
				$key = 'match_'.'String'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_68 = \true; break;
				}
				$result = $res_65;
				$this->setPos($pos_65);
				$key = 'match_'.'Number'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_68 = \true; break;
				}
				$result = $res_65;
				$this->setPos($pos_65);
				$_68 = \false; break;
			}
			while(\false);
			if($_68 === \true) { $_70 = \true; break; }
			$result = $res_63;
			$this->setPos($pos_63);
			$_70 = \false; break;
		}
		while(\false);
		if($_70 === \true) { $_72 = \true; break; }
		$result = $res_61;
		$this->setPos($pos_61);
		$_72 = \false; break;
	}
	while(\false);
	if($_72 === \true) { return $this->finalise($result); }
	if($_72 === \false) { return \false; }
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
	$_109 = \null;
	do {
		$res_75 = $result;
		$pos_75 = $this->pos;
		$_78 = \null;
		do {
			$key = 'match_'.'JSON'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else { $_78 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$_78 = \true; break;
		}
		while(\false);
		if($_78 === \true) { $_109 = \true; break; }
		$result = $res_75;
		$this->setPos($pos_75);
		$_107 = \null;
		do {
			$res_80 = $result;
			$pos_80 = $this->pos;
			$_83 = \null;
			do {
				$key = 'match_'.'SimpleValue'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_83 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_83 = \true; break;
			}
			while(\false);
			if($_83 === \true) { $_107 = \true; break; }
			$result = $res_80;
			$this->setPos($pos_80);
			$_105 = \null;
			do {
				$res_85 = $result;
				$pos_85 = $this->pos;
				$_88 = \null;
				do {
					$key = 'match_'.'FunctionCall'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) { $this->store($result, $subres); }
					else { $_88 = \false; break; }
					if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
					$_88 = \true; break;
				}
				while(\false);
				if($_88 === \true) { $_105 = \true; break; }
				$result = $res_85;
				$this->setPos($pos_85);
				$_103 = \null;
				do {
					$res_90 = $result;
					$pos_90 = $this->pos;
					$_93 = \null;
					do {
						$key = 'match_'.'VariableReference'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) { $this->store($result, $subres); }
						else { $_93 = \false; break; }
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						$_93 = \true; break;
					}
					while(\false);
					if($_93 === \true) { $_103 = \true; break; }
					$result = $res_90;
					$this->setPos($pos_90);
					$_101 = \null;
					do {
						if (\substr($this->string, $this->pos, 1) === '(') {
							$this->addPos(1);
							$result["text"] .= '(';
						}
						else { $_101 = \false; break; }
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						$key = 'match_'.'Expr'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) { $this->store($result, $subres); }
						else { $_101 = \false; break; }
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						if (\substr($this->string, $this->pos, 1) === ')') {
							$this->addPos(1);
							$result["text"] .= ')';
						}
						else { $_101 = \false; break; }
						if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
						$_101 = \true; break;
					}
					while(\false);
					if($_101 === \true) { $_103 = \true; break; }
					$result = $res_90;
					$this->setPos($pos_90);
					$_103 = \false; break;
				}
				while(\false);
				if($_103 === \true) { $_105 = \true; break; }
				$result = $res_85;
				$this->setPos($pos_85);
				$_105 = \false; break;
			}
			while(\false);
			if($_105 === \true) { $_107 = \true; break; }
			$result = $res_80;
			$this->setPos($pos_80);
			$_107 = \false; break;
		}
		while(\false);
		if($_107 === \true) { $_109 = \true; break; }
		$result = $res_75;
		$this->setPos($pos_75);
		$_109 = \false; break;
	}
	while(\false);
	if($_109 === \true) { return $this->finalise($result); }
	if($_109 === \false) { return \false; }
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
	$_124 = \null;
	do {
		$key = 'match_'.'Name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_124 = \false; break; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_124 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_120 = $result;
			$pos_120 = $this->pos;
			$_119 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'Expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_119 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$res_117 = $result;
				$pos_117 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else {
					$result = $res_117;
					$this->setPos($pos_117);
					unset($res_117, $pos_117);
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_119 = \true; break;
			}
			while(\false);
			if($_119 === \false) {
				$result = $res_120;
				$this->setPos($pos_120);
				unset($res_120, $pos_120);
				break;
			}
		}
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
	if($_124 === \true) { return $this->finalise($result); }
	if($_124 === \false) { return \false; }
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
	$_137 = \null;
	do {
		$stack[] = $result; $result = $this->construct($matchrule, "op");
		$_132 = \null;
		do {
			$_130 = \null;
			do {
				$res_127 = $result;
				$pos_127 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === '^') {
					$this->addPos(1);
					$result["text"] .= '^';
					$_130 = \true; break;
				}
				$result = $res_127;
				$this->setPos($pos_127);
				if (($subres = $this->literal('**')) !== \false) {
					$result["text"] .= $subres;
					$_130 = \true; break;
				}
				$result = $res_127;
				$this->setPos($pos_127);
				$_130 = \false; break;
			}
			while(\false);
			if($_130 === \false) { $_132 = \false; break; }
			$_132 = \true; break;
		}
		while(\false);
		if($_132 === \true) {
			$subres = $result; $result = \array_pop($stack);
			$this->store($result, $subres, 'op');
		}
		if($_132 === \false) {
			$result = \array_pop($stack);
			$_137 = \false; break;
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Value'; $pos = $this->pos;
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


/* Power: Value > PowerOp * */
protected $match_Power_typestack = ['Power'];
function match_Power($stack = []) {
	$matchrule = 'Power';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_142 = \null;
	do {
		$key = 'match_'.'Value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_142 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_141 = $result;
			$pos_141 = $this->pos;
			$key = 'match_'.'PowerOp'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else {
				$result = $res_141;
				$this->setPos($pos_141);
				unset($res_141, $pos_141);
				break;
			}
		}
		$_142 = \true; break;
	}
	while(\false);
	if($_142 === \true) { return $this->finalise($result); }
	if($_142 === \false) { return \false; }
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
	$_148 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_148 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Power'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_148 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_148 = \true; break;
	}
	while(\false);
	if($_148 === \true) { return $this->finalise($result); }
	if($_148 === \false) { return \false; }
}


/* UnaryPlus: '+' > operand:Power > */
protected $match_UnaryPlus_typestack = ['UnaryPlus'];
function match_UnaryPlus($stack = []) {
	$matchrule = 'UnaryPlus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_154 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_154 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Power'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_154 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_154 = \true; break;
	}
	while(\false);
	if($_154 === \true) { return $this->finalise($result); }
	if($_154 === \false) { return \false; }
}


/* Negation: '!' > operand:Unary > */
protected $match_Negation_typestack = ['Negation'];
function match_Negation($stack = []) {
	$matchrule = 'Negation';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_160 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '!') {
			$this->addPos(1);
			$result["text"] .= '!';
		}
		else { $_160 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
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


/* Unary: ( Negation | UnaryPlus | UnaryMinus | Power ) */
protected $match_Unary_typestack = ['Unary'];
function match_Unary($stack = []) {
	$matchrule = 'Unary';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_175 = \null;
	do {
		$_173 = \null;
		do {
			$res_162 = $result;
			$pos_162 = $this->pos;
			$key = 'match_'.'Negation'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres);
				$_173 = \true; break;
			}
			$result = $res_162;
			$this->setPos($pos_162);
			$_171 = \null;
			do {
				$res_164 = $result;
				$pos_164 = $this->pos;
				$key = 'match_'.'UnaryPlus'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_171 = \true; break;
				}
				$result = $res_164;
				$this->setPos($pos_164);
				$_169 = \null;
				do {
					$res_166 = $result;
					$pos_166 = $this->pos;
					$key = 'match_'.'UnaryMinus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_169 = \true; break;
					}
					$result = $res_166;
					$this->setPos($pos_166);
					$key = 'match_'.'Power'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_169 = \true; break;
					}
					$result = $res_166;
					$this->setPos($pos_166);
					$_169 = \false; break;
				}
				while(\false);
				if($_169 === \true) { $_171 = \true; break; }
				$result = $res_164;
				$this->setPos($pos_164);
				$_171 = \false; break;
			}
			while(\false);
			if($_171 === \true) { $_173 = \true; break; }
			$result = $res_162;
			$this->setPos($pos_162);
			$_173 = \false; break;
		}
		while(\false);
		if($_173 === \false) { $_175 = \false; break; }
		$_175 = \true; break;
	}
	while(\false);
	if($_175 === \true) { return $this->finalise($result); }
	if($_175 === \false) { return \false; }
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
	$_181 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '*') {
			$this->addPos(1);
			$result["text"] .= '*';
		}
		else { $_181 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_181 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_181 = \true; break;
	}
	while(\false);
	if($_181 === \true) { return $this->finalise($result); }
	if($_181 === \false) { return \false; }
}


/* Div: '/' > operand:Unary > */
protected $match_Div_typestack = ['Div'];
function match_Div($stack = []) {
	$matchrule = 'Div';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_187 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '/') {
			$this->addPos(1);
			$result["text"] .= '/';
		}
		else { $_187 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_187 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_187 = \true; break;
	}
	while(\false);
	if($_187 === \true) { return $this->finalise($result); }
	if($_187 === \false) { return \false; }
}


/* Mod: '%' > operand:Unary > */
protected $match_Mod_typestack = ['Mod'];
function match_Mod($stack = []) {
	$matchrule = 'Mod';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_193 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '%') {
			$this->addPos(1);
			$result["text"] .= '%';
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


/* Intersect: '&' !'&' > operand:Unary > */
protected $match_Intersect_typestack = ['Intersect'];
function match_Intersect($stack = []) {
	$matchrule = 'Intersect';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_200 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '&') {
			$this->addPos(1);
			$result["text"] .= '&';
		}
		else { $_200 = \false; break; }
		$res_196 = $result;
		$pos_196 = $this->pos;
		if (\substr($this->string, $this->pos, 1) === '&') {
			$this->addPos(1);
			$result["text"] .= '&';
			$result = $res_196;
			$this->setPos($pos_196);
			$_200 = \false; break;
		}
		else {
			$result = $res_196;
			$this->setPos($pos_196);
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unary'; $pos = $this->pos;
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


/* ImplicitTimes: operand:Power > */
protected $match_ImplicitTimes_typestack = ['ImplicitTimes'];
function match_ImplicitTimes($stack = []) {
	$matchrule = 'ImplicitTimes';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_204 = \null;
	do {
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


/* Property: '[' > operand:Expr > ']' > */
protected $match_Property_typestack = ['Property'];
function match_Property($stack = []) {
	$matchrule = 'Property';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_212 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '[') {
			$this->addPos(1);
			$result["text"] .= '[';
		}
		else { $_212 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_212 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ']') {
			$this->addPos(1);
			$result["text"] .= ']';
		}
		else { $_212 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_212 = \true; break;
	}
	while(\false);
	if($_212 === \true) { return $this->finalise($result); }
	if($_212 === \false) { return \false; }
}


/* Product: Unary > ( Times | Div | Mod | Intersect | Property | ImplicitTimes ) * */
protected $match_Product_typestack = ['Product'];
function match_Product($stack = []) {
	$matchrule = 'Product';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_239 = \null;
	do {
		$key = 'match_'.'Unary'; $pos = $this->pos;
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
					$res_216 = $result;
					$pos_216 = $this->pos;
					$key = 'match_'.'Times'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_235 = \true; break;
					}
					$result = $res_216;
					$this->setPos($pos_216);
					$_233 = \null;
					do {
						$res_218 = $result;
						$pos_218 = $this->pos;
						$key = 'match_'.'Div'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_233 = \true; break;
						}
						$result = $res_218;
						$this->setPos($pos_218);
						$_231 = \null;
						do {
							$res_220 = $result;
							$pos_220 = $this->pos;
							$key = 'match_'.'Mod'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres);
								$_231 = \true; break;
							}
							$result = $res_220;
							$this->setPos($pos_220);
							$_229 = \null;
							do {
								$res_222 = $result;
								$pos_222 = $this->pos;
								$key = 'match_'.'Intersect'; $pos = $this->pos;
								$subres = $this->packhas($key, $pos)
									? $this->packread($key, $pos)
									: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
								if ($subres !== \false) {
									$this->store($result, $subres);
									$_229 = \true; break;
								}
								$result = $res_222;
								$this->setPos($pos_222);
								$_227 = \null;
								do {
									$res_224 = $result;
									$pos_224 = $this->pos;
									$key = 'match_'.'Property'; $pos = $this->pos;
									$subres = $this->packhas($key, $pos)
										? $this->packread($key, $pos)
										: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
									if ($subres !== \false) {
										$this->store($result, $subres);
										$_227 = \true; break;
									}
									$result = $res_224;
									$this->setPos($pos_224);
									$key = 'match_'.'ImplicitTimes'; $pos = $this->pos;
									$subres = $this->packhas($key, $pos)
										? $this->packread($key, $pos)
										: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
									if ($subres !== \false) {
										$this->store($result, $subres);
										$_227 = \true; break;
									}
									$result = $res_224;
									$this->setPos($pos_224);
									$_227 = \false; break;
								}
								while(\false);
								if($_227 === \true) { $_229 = \true; break; }
								$result = $res_222;
								$this->setPos($pos_222);
								$_229 = \false; break;
							}
							while(\false);
							if($_229 === \true) { $_231 = \true; break; }
							$result = $res_220;
							$this->setPos($pos_220);
							$_231 = \false; break;
						}
						while(\false);
						if($_231 === \true) { $_233 = \true; break; }
						$result = $res_218;
						$this->setPos($pos_218);
						$_233 = \false; break;
					}
					while(\false);
					if($_233 === \true) { $_235 = \true; break; }
					$result = $res_216;
					$this->setPos($pos_216);
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

public function Product_Unary (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Product_Power (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Product_Times (&$result, $sub) {
        $result['val'] = $this->multiply($result['val'], $sub['operand']['val']);
    }

public function Product_Intersect (&$result, $sub) {
        $result['val'] = $this->intersect($result['val'], $sub['operand']['val']);
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
	$_245 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_245 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_245 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_245 = \true; break;
	}
	while(\false);
	if($_245 === \true) { return $this->finalise($result); }
	if($_245 === \false) { return \false; }
}


/* Minus: '-' > operand:Product > */
protected $match_Minus_typestack = ['Minus'];
function match_Minus($stack = []) {
	$matchrule = 'Minus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_251 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_251 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
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


/* Union: '|' !'|' > operand:Product > */
protected $match_Union_typestack = ['Union'];
function match_Union($stack = []) {
	$matchrule = 'Union';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_258 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '|') {
			$this->addPos(1);
			$result["text"] .= '|';
		}
		else { $_258 = \false; break; }
		$res_254 = $result;
		$pos_254 = $this->pos;
		if (\substr($this->string, $this->pos, 1) === '|') {
			$this->addPos(1);
			$result["text"] .= '|';
			$result = $res_254;
			$this->setPos($pos_254);
			$_258 = \false; break;
		}
		else {
			$result = $res_254;
			$this->setPos($pos_254);
		}
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


/* Sum: Product > ( Plus | Minus | Union ) * */
protected $match_Sum_typestack = ['Sum'];
function match_Sum($stack = []) {
	$matchrule = 'Sum';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_273 = \null;
	do {
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_273 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_272 = $result;
			$pos_272 = $this->pos;
			$_271 = \null;
			do {
				$_269 = \null;
				do {
					$res_262 = $result;
					$pos_262 = $this->pos;
					$key = 'match_'.'Plus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_269 = \true; break;
					}
					$result = $res_262;
					$this->setPos($pos_262);
					$_267 = \null;
					do {
						$res_264 = $result;
						$pos_264 = $this->pos;
						$key = 'match_'.'Minus'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_267 = \true; break;
						}
						$result = $res_264;
						$this->setPos($pos_264);
						$key = 'match_'.'Union'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_267 = \true; break;
						}
						$result = $res_264;
						$this->setPos($pos_264);
						$_267 = \false; break;
					}
					while(\false);
					if($_267 === \true) { $_269 = \true; break; }
					$result = $res_262;
					$this->setPos($pos_262);
					$_269 = \false; break;
				}
				while(\false);
				if($_269 === \false) { $_271 = \false; break; }
				$_271 = \true; break;
			}
			while(\false);
			if($_271 === \false) {
				$result = $res_272;
				$this->setPos($pos_272);
				unset($res_272, $pos_272);
				break;
			}
		}
		$_273 = \true; break;
	}
	while(\false);
	if($_273 === \true) { return $this->finalise($result); }
	if($_273 === \false) { return \false; }
}

public function Sum_Product (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Sum_Plus (&$result, $sub) {
        $result['val'] = $this->add($result['val'], $sub['operand']['val']);
    }

public function Sum_Minus (&$result, $sub) {
        $result['val'] = $this->subtract($result['val'], $sub['operand']['val']);
    }

public function Sum_Union (&$result, $sub) {
        $result['val'] = $this->union($result['val'], $sub['operand']['val']);
    }

/* VariableAssignment: Variable > '=' > Expr */
protected $match_VariableAssignment_typestack = ['VariableAssignment'];
function match_VariableAssignment($stack = []) {
	$matchrule = 'VariableAssignment';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_280 = \null;
	do {
		$key = 'match_'.'Variable'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_280 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '=') {
			$this->addPos(1);
			$result["text"] .= '=';
		}
		else { $_280 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_280 = \false; break; }
		$_280 = \true; break;
	}
	while(\false);
	if($_280 === \true) { return $this->finalise($result); }
	if($_280 === \false) { return \false; }
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
	$_299 = \null;
	do {
		$key = 'match_'.'Name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_299 = \false; break; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_299 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_292 = $result;
			$pos_292 = $this->pos;
			$_291 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'Variable'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_291 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$res_289 = $result;
				$pos_289 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else {
					$result = $res_289;
					$this->setPos($pos_289);
					unset($res_289, $pos_289);
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_291 = \true; break;
			}
			while(\false);
			if($_291 === \false) {
				$result = $res_292;
				$this->setPos($pos_292);
				unset($res_292, $pos_292);
				break;
			}
		}
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_299 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '=') {
			$this->addPos(1);
			$result["text"] .= '=';
		}
		else { $_299 = \false; break; }
		$res_296 = $result;
		$pos_296 = $this->pos;
		if (($subres = $this->rx('/[=~]/')) !== \false) {
			$result["text"] .= $subres;
			$result = $res_296;
			$this->setPos($pos_296);
			$_299 = \false; break;
		}
		else {
			$result = $res_296;
			$this->setPos($pos_296);
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'FunctionBody'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_299 = \false; break; }
		$_299 = \true; break;
	}
	while(\false);
	if($_299 === \true) { return $this->finalise($result); }
	if($_299 === \false) { return \false; }
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
	$_305 = \null;
	do {
		if (($subres = $this->literal('<<')) !== \false) { $result["text"] .= $subres; }
		else { $_305 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Sum'; $pos = $this->pos;
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


/* ShiftRight: '>>' > operand:Sum > */
protected $match_ShiftRight_typestack = ['ShiftRight'];
function match_ShiftRight($stack = []) {
	$matchrule = 'ShiftRight';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_311 = \null;
	do {
		if (($subres = $this->literal('>>')) !== \false) { $result["text"] .= $subres; }
		else { $_311 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Sum'; $pos = $this->pos;
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


/* BitShift: Sum > (ShiftRight | ShiftLeft) * */
protected $match_BitShift_typestack = ['BitShift'];
function match_BitShift($stack = []) {
	$matchrule = 'BitShift';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_322 = \null;
	do {
		$key = 'match_'.'Sum'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_322 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_321 = $result;
			$pos_321 = $this->pos;
			$_320 = \null;
			do {
				$_318 = \null;
				do {
					$res_315 = $result;
					$pos_315 = $this->pos;
					$key = 'match_'.'ShiftRight'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_318 = \true; break;
					}
					$result = $res_315;
					$this->setPos($pos_315);
					$key = 'match_'.'ShiftLeft'; $pos = $this->pos;
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
			if($_320 === \false) {
				$result = $res_321;
				$this->setPos($pos_321);
				unset($res_321, $pos_321);
				break;
			}
		}
		$_322 = \true; break;
	}
	while(\false);
	if($_322 === \true) { return $this->finalise($result); }
	if($_322 === \false) { return \false; }
}

public function BitShift_Sum (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function BitShift_ShiftLeft (&$result, $sub) {
        $result['val'] = $this->append($result['val'], $sub['operand']['val']);
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
	$_328 = \null;
	do {
		if (($subres = $this->literal('===')) !== \false) { $result["text"] .= $subres; }
		else { $_328 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_328 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_328 = \true; break;
	}
	while(\false);
	if($_328 === \true) { return $this->finalise($result); }
	if($_328 === \false) { return \false; }
}


/* StrictNotEqual: '!==' > operand:BitShift > */
protected $match_StrictNotEqual_typestack = ['StrictNotEqual'];
function match_StrictNotEqual($stack = []) {
	$matchrule = 'StrictNotEqual';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_334 = \null;
	do {
		if (($subres = $this->literal('!==')) !== \false) { $result["text"] .= $subres; }
		else { $_334 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_334 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_334 = \true; break;
	}
	while(\false);
	if($_334 === \true) { return $this->finalise($result); }
	if($_334 === \false) { return \false; }
}


/* Spaceship: '<=>' > operand:BitShift > */
protected $match_Spaceship_typestack = ['Spaceship'];
function match_Spaceship($stack = []) {
	$matchrule = 'Spaceship';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_340 = \null;
	do {
		if (($subres = $this->literal('<=>')) !== \false) { $result["text"] .= $subres; }
		else { $_340 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_340 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_340 = \true; break;
	}
	while(\false);
	if($_340 === \true) { return $this->finalise($result); }
	if($_340 === \false) { return \false; }
}


/* Equal: '==' > operand:BitShift > */
protected $match_Equal_typestack = ['Equal'];
function match_Equal($stack = []) {
	$matchrule = 'Equal';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_346 = \null;
	do {
		if (($subres = $this->literal('==')) !== \false) { $result["text"] .= $subres; }
		else { $_346 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_346 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_346 = \true; break;
	}
	while(\false);
	if($_346 === \true) { return $this->finalise($result); }
	if($_346 === \false) { return \false; }
}


/* Match: '=~' > operand:BitShift > */
protected $match_Match_typestack = ['Match'];
function match_Match($stack = []) {
	$matchrule = 'Match';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_352 = \null;
	do {
		if (($subres = $this->literal('=~')) !== \false) { $result["text"] .= $subres; }
		else { $_352 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_352 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_352 = \true; break;
	}
	while(\false);
	if($_352 === \true) { return $this->finalise($result); }
	if($_352 === \false) { return \false; }
}


/* NotEqual: '!=' > operand:BitShift > */
protected $match_NotEqual_typestack = ['NotEqual'];
function match_NotEqual($stack = []) {
	$matchrule = 'NotEqual';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_358 = \null;
	do {
		if (($subres = $this->literal('!=')) !== \false) { $result["text"] .= $subres; }
		else { $_358 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_358 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_358 = \true; break;
	}
	while(\false);
	if($_358 === \true) { return $this->finalise($result); }
	if($_358 === \false) { return \false; }
}


/* GreaterEqualThan: '>=' > operand:BitShift > */
protected $match_GreaterEqualThan_typestack = ['GreaterEqualThan'];
function match_GreaterEqualThan($stack = []) {
	$matchrule = 'GreaterEqualThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_364 = \null;
	do {
		if (($subres = $this->literal('>=')) !== \false) { $result["text"] .= $subres; }
		else { $_364 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_364 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_364 = \true; break;
	}
	while(\false);
	if($_364 === \true) { return $this->finalise($result); }
	if($_364 === \false) { return \false; }
}


/* LessEqualThan: '<=' > operand:BitShift > */
protected $match_LessEqualThan_typestack = ['LessEqualThan'];
function match_LessEqualThan($stack = []) {
	$matchrule = 'LessEqualThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_370 = \null;
	do {
		if (($subres = $this->literal('<=')) !== \false) { $result["text"] .= $subres; }
		else { $_370 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_370 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_370 = \true; break;
	}
	while(\false);
	if($_370 === \true) { return $this->finalise($result); }
	if($_370 === \false) { return \false; }
}


/* GreaterThan: '>' > operand:BitShift > */
protected $match_GreaterThan_typestack = ['GreaterThan'];
function match_GreaterThan($stack = []) {
	$matchrule = 'GreaterThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_376 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '>') {
			$this->addPos(1);
			$result["text"] .= '>';
		}
		else { $_376 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_376 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_376 = \true; break;
	}
	while(\false);
	if($_376 === \true) { return $this->finalise($result); }
	if($_376 === \false) { return \false; }
}


/* LessThan: '<' > operand:BitShift > */
protected $match_LessThan_typestack = ['LessThan'];
function match_LessThan($stack = []) {
	$matchrule = 'LessThan';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_382 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '<') {
			$this->addPos(1);
			$result["text"] .= '<';
		}
		else { $_382 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_382 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_382 = \true; break;
	}
	while(\false);
	if($_382 === \true) { return $this->finalise($result); }
	if($_382 === \false) { return \false; }
}


/* In: 'in' !/[A-Za-z0-9_]/ > operand:BitShift > */
protected $match_In_typestack = ['In'];
function match_In($stack = []) {
	$matchrule = 'In';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_389 = \null;
	do {
		if (($subres = $this->literal('in')) !== \false) { $result["text"] .= $subres; }
		else { $_389 = \false; break; }
		$res_385 = $result;
		$pos_385 = $this->pos;
		if (($subres = $this->rx('/[A-Za-z0-9_]/')) !== \false) {
			$result["text"] .= $subres;
			$result = $res_385;
			$this->setPos($pos_385);
			$_389 = \false; break;
		}
		else {
			$result = $res_385;
			$this->setPos($pos_385);
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_389 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_389 = \true; break;
	}
	while(\false);
	if($_389 === \true) { return $this->finalise($result); }
	if($_389 === \false) { return \false; }
}


/* Compare: BitShift > (StrictEqual | StrictNotEqual | Spaceship | Equal | Match | NotEqual | GreaterEqualThan | GreaterThan | LessEqualThan | LessThan | In ) * */
protected $match_Compare_typestack = ['Compare'];
function match_Compare($stack = []) {
	$matchrule = 'Compare';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_436 = \null;
	do {
		$key = 'match_'.'BitShift'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_436 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_435 = $result;
			$pos_435 = $this->pos;
			$_434 = \null;
			do {
				$_432 = \null;
				do {
					$res_393 = $result;
					$pos_393 = $this->pos;
					$key = 'match_'.'StrictEqual'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_432 = \true; break;
					}
					$result = $res_393;
					$this->setPos($pos_393);
					$_430 = \null;
					do {
						$res_395 = $result;
						$pos_395 = $this->pos;
						$key = 'match_'.'StrictNotEqual'; $pos = $this->pos;
						$subres = $this->packhas($key, $pos)
							? $this->packread($key, $pos)
							: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
						if ($subres !== \false) {
							$this->store($result, $subres);
							$_430 = \true; break;
						}
						$result = $res_395;
						$this->setPos($pos_395);
						$_428 = \null;
						do {
							$res_397 = $result;
							$pos_397 = $this->pos;
							$key = 'match_'.'Spaceship'; $pos = $this->pos;
							$subres = $this->packhas($key, $pos)
								? $this->packread($key, $pos)
								: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
							if ($subres !== \false) {
								$this->store($result, $subres);
								$_428 = \true; break;
							}
							$result = $res_397;
							$this->setPos($pos_397);
							$_426 = \null;
							do {
								$res_399 = $result;
								$pos_399 = $this->pos;
								$key = 'match_'.'Equal'; $pos = $this->pos;
								$subres = $this->packhas($key, $pos)
									? $this->packread($key, $pos)
									: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
								if ($subres !== \false) {
									$this->store($result, $subres);
									$_426 = \true; break;
								}
								$result = $res_399;
								$this->setPos($pos_399);
								$_424 = \null;
								do {
									$res_401 = $result;
									$pos_401 = $this->pos;
									$key = 'match_'.'Match'; $pos = $this->pos;
									$subres = $this->packhas($key, $pos)
										? $this->packread($key, $pos)
										: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
									if ($subres !== \false) {
										$this->store($result, $subres);
										$_424 = \true; break;
									}
									$result = $res_401;
									$this->setPos($pos_401);
									$_422 = \null;
									do {
										$res_403 = $result;
										$pos_403 = $this->pos;
										$key = 'match_'.'NotEqual'; $pos = $this->pos;
										$subres = $this->packhas($key, $pos)
											? $this->packread($key, $pos)
											: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
										if ($subres !== \false) {
											$this->store($result, $subres);
											$_422 = \true; break;
										}
										$result = $res_403;
										$this->setPos($pos_403);
										$_420 = \null;
										do {
											$res_405 = $result;
											$pos_405 = $this->pos;
											$key = 'match_'.'GreaterEqualThan'; $pos = $this->pos;
											$subres = $this->packhas($key, $pos)
												? $this->packread($key, $pos)
												: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
											if ($subres !== \false) {
												$this->store($result, $subres);
												$_420 = \true; break;
											}
											$result = $res_405;
											$this->setPos($pos_405);
											$_418 = \null;
											do {
												$res_407 = $result;
												$pos_407 = $this->pos;
												$key = 'match_'.'GreaterThan'; $pos = $this->pos;
												$subres = $this->packhas($key, $pos)
													? $this->packread($key, $pos)
													: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
												if ($subres !== \false) {
													$this->store($result, $subres);
													$_418 = \true; break;
												}
												$result = $res_407;
												$this->setPos($pos_407);
												$_416 = \null;
												do {
													$res_409 = $result;
													$pos_409 = $this->pos;
													$key = 'match_'.'LessEqualThan'; $pos = $this->pos;
													$subres = $this->packhas($key, $pos)
														? $this->packread($key, $pos)
														: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
													if ($subres !== \false) {
														$this->store($result, $subres);
														$_416 = \true; break;
													}
													$result = $res_409;
													$this->setPos($pos_409);
													$_414 = \null;
													do {
														$res_411 = $result;
														$pos_411 = $this->pos;
														$key = 'match_'.'LessThan'; $pos = $this->pos;
														$subres = $this->packhas($key, $pos)
															? $this->packread($key, $pos)
															: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
														if ($subres !== \false) {
															$this->store($result, $subres);
															$_414 = \true; break;
														}
														$result = $res_411;
														$this->setPos($pos_411);
														$key = 'match_'.'In'; $pos = $this->pos;
														$subres = $this->packhas($key, $pos)
															? $this->packread($key, $pos)
															: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
														if ($subres !== \false) {
															$this->store($result, $subres);
															$_414 = \true; break;
														}
														$result = $res_411;
														$this->setPos($pos_411);
														$_414 = \false; break;
													}
													while(\false);
													if($_414 === \true) { $_416 = \true; break; }
													$result = $res_409;
													$this->setPos($pos_409);
													$_416 = \false; break;
												}
												while(\false);
												if($_416 === \true) { $_418 = \true; break; }
												$result = $res_407;
												$this->setPos($pos_407);
												$_418 = \false; break;
											}
											while(\false);
											if($_418 === \true) { $_420 = \true; break; }
											$result = $res_405;
											$this->setPos($pos_405);
											$_420 = \false; break;
										}
										while(\false);
										if($_420 === \true) { $_422 = \true; break; }
										$result = $res_403;
										$this->setPos($pos_403);
										$_422 = \false; break;
									}
									while(\false);
									if($_422 === \true) { $_424 = \true; break; }
									$result = $res_401;
									$this->setPos($pos_401);
									$_424 = \false; break;
								}
								while(\false);
								if($_424 === \true) { $_426 = \true; break; }
								$result = $res_399;
								$this->setPos($pos_399);
								$_426 = \false; break;
							}
							while(\false);
							if($_426 === \true) { $_428 = \true; break; }
							$result = $res_397;
							$this->setPos($pos_397);
							$_428 = \false; break;
						}
						while(\false);
						if($_428 === \true) { $_430 = \true; break; }
						$result = $res_395;
						$this->setPos($pos_395);
						$_430 = \false; break;
					}
					while(\false);
					if($_430 === \true) { $_432 = \true; break; }
					$result = $res_393;
					$this->setPos($pos_393);
					$_432 = \false; break;
				}
				while(\false);
				if($_432 === \false) { $_434 = \false; break; }
				$_434 = \true; break;
			}
			while(\false);
			if($_434 === \false) {
				$result = $res_435;
				$this->setPos($pos_435);
				unset($res_435, $pos_435);
				break;
			}
		}
		$_436 = \true; break;
	}
	while(\false);
	if($_436 === \true) { return $this->finalise($result); }
	if($_436 === \false) { return \false; }
}

public function Compare_BitShift (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Compare_Spaceship (&$result, $sub) {
        $result['val'] = $this->spaceship($result['val'], $sub['operand']['val']);
    }

public function Compare_In (&$result, $sub) {
        $result['val'] = $this->member($result['val'], $sub['operand']['val']);
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
	$_442 = \null;
	do {
		if (($subres = $this->literal('&&')) !== \false) { $result["text"] .= $subres; }
		else { $_442 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_442 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_442 = \true; break;
	}
	while(\false);
	if($_442 === \true) { return $this->finalise($result); }
	if($_442 === \false) { return \false; }
}


/* Or: '||' > operand:Compare > */
protected $match_Or_typestack = ['Or'];
function match_Or($stack = []) {
	$matchrule = 'Or';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_448 = \null;
	do {
		if (($subres = $this->literal('||')) !== \false) { $result["text"] .= $subres; }
		else { $_448 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_448 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_448 = \true; break;
	}
	while(\false);
	if($_448 === \true) { return $this->finalise($result); }
	if($_448 === \false) { return \false; }
}


/* Boolean: Compare > (And | Or ) * */
protected $match_Boolean_typestack = ['Boolean'];
function match_Boolean($stack = []) {
	$matchrule = 'Boolean';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_459 = \null;
	do {
		$key = 'match_'.'Compare'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_459 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_458 = $result;
			$pos_458 = $this->pos;
			$_457 = \null;
			do {
				$_455 = \null;
				do {
					$res_452 = $result;
					$pos_452 = $this->pos;
					$key = 'match_'.'And'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_455 = \true; break;
					}
					$result = $res_452;
					$this->setPos($pos_452);
					$key = 'match_'.'Or'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_455 = \true; break;
					}
					$result = $res_452;
					$this->setPos($pos_452);
					$_455 = \false; break;
				}
				while(\false);
				if($_455 === \false) { $_457 = \false; break; }
				$_457 = \true; break;
			}
			while(\false);
			if($_457 === \false) {
				$result = $res_458;
				$this->setPos($pos_458);
				unset($res_458, $pos_458);
				break;
			}
		}
		$_459 = \true; break;
	}
	while(\false);
	if($_459 === \true) { return $this->finalise($result); }
	if($_459 === \false) { return \false; }
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

/* TernaryTail: '?' > iftrue:Expr > ':' > iffalse:Ternary > */
protected $match_TernaryTail_typestack = ['TernaryTail'];
function match_TernaryTail($stack = []) {
	$matchrule = 'TernaryTail';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_469 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '?') {
			$this->addPos(1);
			$result["text"] .= '?';
		}
		else { $_469 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "iftrue");
		}
		else { $_469 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ':') {
			$this->addPos(1);
			$result["text"] .= ':';
		}
		else { $_469 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Ternary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "iffalse");
		}
		else { $_469 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_469 = \true; break;
	}
	while(\false);
	if($_469 === \true) { return $this->finalise($result); }
	if($_469 === \false) { return \false; }
}


/* Ternary: Boolean > TernaryTail ? */
protected $match_Ternary_typestack = ['Ternary'];
function match_Ternary($stack = []) {
	$matchrule = 'Ternary';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_474 = \null;
	do {
		$key = 'match_'.'Boolean'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_474 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$res_473 = $result;
		$pos_473 = $this->pos;
		$key = 'match_'.'TernaryTail'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else {
			$result = $res_473;
			$this->setPos($pos_473);
			unset($res_473, $pos_473);
		}
		$_474 = \true; break;
	}
	while(\false);
	if($_474 === \true) { return $this->finalise($result); }
	if($_474 === \false) { return \false; }
}

public function Ternary_Boolean (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

public function Ternary_TernaryTail (&$result, $sub) {
        $condition = $result['val'];
        $result['val'] = $condition['value'] ? $sub['iftrue']['val'] : $sub['iffalse']['val'];
    }

/* Expr: Ternary */
protected $match_Expr_typestack = ['Expr'];
function match_Expr($stack = []) {
	$matchrule = 'Expr';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$key = 'match_'.'Ternary'; $pos = $this->pos;
	$subres = $this->packhas($key, $pos)
		? $this->packread($key, $pos)
		: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
	if ($subres !== \false) {
		$this->store($result, $subres);
		return $this->finalise($result);
	}
	else { return \false; }
}

public function Expr_Ternary (&$result, $sub) {
        $result['val'] = $sub['val'];
    }

/* Start: (VariableAssignment | FunctionAssignment | Expr ) ';'? */
protected $match_Start_typestack = ['Start'];
function match_Start($stack = []) {
	$matchrule = 'Start';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_489 = \null;
	do {
		$_486 = \null;
		do {
			$_484 = \null;
			do {
				$res_477 = $result;
				$pos_477 = $this->pos;
				$key = 'match_'.'VariableAssignment'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_484 = \true; break;
				}
				$result = $res_477;
				$this->setPos($pos_477);
				$_482 = \null;
				do {
					$res_479 = $result;
					$pos_479 = $this->pos;
					$key = 'match_'.'FunctionAssignment'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_482 = \true; break;
					}
					$result = $res_479;
					$this->setPos($pos_479);
					$key = 'match_'.'Expr'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_482 = \true; break;
					}
					$result = $res_479;
					$this->setPos($pos_479);
					$_482 = \false; break;
				}
				while(\false);
				if($_482 === \true) { $_484 = \true; break; }
				$result = $res_477;
				$this->setPos($pos_477);
				$_484 = \false; break;
			}
			while(\false);
			if($_484 === \false) { $_486 = \false; break; }
			$_486 = \true; break;
		}
		while(\false);
		if($_486 === \false) { $_489 = \false; break; }
		$res_488 = $result;
		$pos_488 = $this->pos;
		if (\substr($this->string, $this->pos, 1) === ';') {
			$this->addPos(1);
			$result["text"] .= ';';
		}
		else {
			$result = $res_488;
			$this->setPos($pos_488);
			unset($res_488, $pos_488);
		}
		$_489 = \true; break;
	}
	while(\false);
	if($_489 === \true) { return $this->finalise($result); }
	if($_489 === \false) { return \false; }
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

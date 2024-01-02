<?php
namespace jcubic;

use hafriedlander\Peg;
use ReflectionFunction;

class Parser extends Peg\Parser\Basic {
  public $variables;
  public $functions;
  private $constants;
  public function __construct($expr, &$variables, &$constants, &$functions) {
      parent::__construct($expr);
      $this->variables = $variables;
      $this->constants = $constants;
      $this->functions = $functions;
  }

/* Name: /[A-Za-z]+/ */
protected $match_Name_typestack = ['Name'];
function match_Name($stack = []) {
	$matchrule = 'Name';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/[A-Za-z]+/')) !== \false) {
		$result["text"] .= $subres;
		return $this->finalise($result);
	}
	else { return \false; }
}

public function Name_Name (&$result, $sub) {
        $result['val'] = $sub['text'];
    }

/* Number: /[0-9]+/ */
protected $match_Number_typestack = ['Number'];
function match_Number($stack = []) {
	$matchrule = 'Number';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	if (($subres = $this->rx('/[0-9]+/')) !== \false) {
		$result["text"] .= $subres;
		return $this->finalise($result);
	}
	else { return \false; }
}


/* Value: Name > | Number > | '(' > Expr > ')' > */
protected $match_Value_typestack = ['Value'];
function match_Value($stack = []) {
	$matchrule = 'Value';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_22 = \null;
	do {
		$res_2 = $result;
		$pos_2 = $this->pos;
		$_5 = \null;
		do {
			$key = 'match_'.'Name'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) { $this->store($result, $subres); }
			else { $_5 = \false; break; }
			if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
			$_5 = \true; break;
		}
		while(\false);
		if($_5 === \true) { $_22 = \true; break; }
		$result = $res_2;
		$this->setPos($pos_2);
		$_20 = \null;
		do {
			$res_7 = $result;
			$pos_7 = $this->pos;
			$_10 = \null;
			do {
				$key = 'match_'.'Number'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_10 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_10 = \true; break;
			}
			while(\false);
			if($_10 === \true) { $_20 = \true; break; }
			$result = $res_7;
			$this->setPos($pos_7);
			$_18 = \null;
			do {
				if (\substr($this->string, $this->pos, 1) === '(') {
					$this->addPos(1);
					$result["text"] .= '(';
				}
				else { $_18 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'Expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_18 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				if (\substr($this->string, $this->pos, 1) === ')') {
					$this->addPos(1);
					$result["text"] .= ')';
				}
				else { $_18 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_18 = \true; break;
			}
			while(\false);
			if($_18 === \true) { $_20 = \true; break; }
			$result = $res_7;
			$this->setPos($pos_7);
			$_20 = \false; break;
		}
		while(\false);
		if($_20 === \true) { $_22 = \true; break; }
		$result = $res_2;
		$this->setPos($pos_2);
		$_22 = \false; break;
	}
	while(\false);
	if($_22 === \true) { return $this->finalise($result); }
	if($_22 === \false) { return \false; }
}

public function Value_Number (&$result, $sub ) {
        $result['val'] = $sub['text'];
    }

public function Value_Expr (&$result, $sub ) {
        $result['val'] = $sub['val'];
    }

public function Value_Name (&$result, $sub) {
        $name = $sub['text'];
        if (array_key_exists($name, $this->constants)) {
            $result['val'] = $this->constants[$name];
        } else if (array_key_exists($name, $this->variables)) {
            $result['val'] = $this->variables[$name];
        } else {
            throw new \Exception("Variable '$name' not found");
        }
    }

/* Call: Name "(" > ( > Expr > ","? > ) * > ")" > */
protected $match_Call_typestack = ['Call'];
function match_Call($stack = []) {
	$matchrule = 'Call';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_37 = \null;
	do {
		$key = 'match_'.'Name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_37 = \false; break; }
		if (\substr($this->string, $this->pos, 1) === '(') {
			$this->addPos(1);
			$result["text"] .= '(';
		}
		else { $_37 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_33 = $result;
			$pos_33 = $this->pos;
			$_32 = \null;
			do {
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$key = 'match_'.'Expr'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) { $this->store($result, $subres); }
				else { $_32 = \false; break; }
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$res_30 = $result;
				$pos_30 = $this->pos;
				if (\substr($this->string, $this->pos, 1) === ',') {
					$this->addPos(1);
					$result["text"] .= ',';
				}
				else {
					$result = $res_30;
					$this->setPos($pos_30);
					unset($res_30, $pos_30);
				}
				if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
				$_32 = \true; break;
			}
			while(\false);
			if($_32 === \false) {
				$result = $res_33;
				$this->setPos($pos_33);
				unset($res_33, $pos_33);
				break;
			}
		}
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === ')') {
			$this->addPos(1);
			$result["text"] .= ')';
		}
		else { $_37 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_37 = \true; break;
	}
	while(\false);
	if($_37 === \true) { return $this->finalise($result); }
	if($_37 === \false) { return \false; }
}

public function Call_Name (&$result, $sub) {
       $name = $sub['text'];
       $result['val'] = [
           "args" => [],
           "name" => $name
       ];
   }

public function Call_Expr (&$result, $sub) {
       array_push($result['val']['args'], $sub['val']);
   }

/* Negation: '-' > operand:Value > */
protected $match_Negation_typestack = ['Negation'];
function match_Negation($stack = []) {
	$matchrule = 'Negation';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_43 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_43 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_43 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_43 = \true; break;
	}
	while(\false);
	if($_43 === \true) { return $this->finalise($result); }
	if($_43 === \false) { return \false; }
}


/* ToInt: '+' > operand:Value > */
protected $match_ToInt_typestack = ['ToInt'];
function match_ToInt($stack = []) {
	$matchrule = 'ToInt';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_49 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_49 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_49 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_49 = \true; break;
	}
	while(\false);
	if($_49 === \true) { return $this->finalise($result); }
	if($_49 === \false) { return \false; }
}


/* Unnary: (Call | Negation | ToInt | Value ) */
protected $match_Unnary_typestack = ['Unnary'];
function match_Unnary($stack = []) {
	$matchrule = 'Unnary';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_64 = \null;
	do {
		$_62 = \null;
		do {
			$res_51 = $result;
			$pos_51 = $this->pos;
			$key = 'match_'.'Call'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres);
				$_62 = \true; break;
			}
			$result = $res_51;
			$this->setPos($pos_51);
			$_60 = \null;
			do {
				$res_53 = $result;
				$pos_53 = $this->pos;
				$key = 'match_'.'Negation'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_60 = \true; break;
				}
				$result = $res_53;
				$this->setPos($pos_53);
				$_58 = \null;
				do {
					$res_55 = $result;
					$pos_55 = $this->pos;
					$key = 'match_'.'ToInt'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_58 = \true; break;
					}
					$result = $res_55;
					$this->setPos($pos_55);
					$key = 'match_'.'Value'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_58 = \true; break;
					}
					$result = $res_55;
					$this->setPos($pos_55);
					$_58 = \false; break;
				}
				while(\false);
				if($_58 === \true) { $_60 = \true; break; }
				$result = $res_53;
				$this->setPos($pos_53);
				$_60 = \false; break;
			}
			while(\false);
			if($_60 === \true) { $_62 = \true; break; }
			$result = $res_51;
			$this->setPos($pos_51);
			$_62 = \false; break;
		}
		while(\false);
		if($_62 === \false) { $_64 = \false; break; }
		$_64 = \true; break;
	}
	while(\false);
	if($_64 === \true) { return $this->finalise($result); }
	if($_64 === \false) { return \false; }
}

public function Unnary_ToInt (&$result, $sub) {
        $val = $sub['operand']['val'];
        if (is_string($val)) {
            $val = floatval($val);
        }
        $result['val'] = $val;
   }

public function Unnary_Call (&$result, $sub) {
       $name = $sub['val']['name'];
       if (!array_key_exists($name, $this->functions)) {
           throw new \Exception("function '$name' doesn't exists");
       }
       $function = new ReflectionFunction($this->functions[$name]);
       $params_count = $function->getNumberOfParameters();
       $args = $sub['val']['args'];
       $args_count = count($args);
       if ($params_count != $args_count) {
           throw new \Exception("Function '$name' expected $params_count params got $args_count");
       }
       $result['val'] = $function->invokeArgs($args);
   }

public function Unnary_Value (&$result, $sub) {
       $result['val'] = $sub['val'];
   }

public function Unnary_Negation (&$result, $sub) {
       $result['val'] = $sub['operand']['val'] * -1;
   }

/* Times: '*' > operand:Unnary > */
protected $match_Times_typestack = ['Times'];
function match_Times($stack = []) {
	$matchrule = 'Times';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_70 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '*') {
			$this->addPos(1);
			$result["text"] .= '*';
		}
		else { $_70 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unnary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_70 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_70 = \true; break;
	}
	while(\false);
	if($_70 === \true) { return $this->finalise($result); }
	if($_70 === \false) { return \false; }
}


/* Div: '/' > operand:Unnary > */
protected $match_Div_typestack = ['Div'];
function match_Div($stack = []) {
	$matchrule = 'Div';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_76 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '/') {
			$this->addPos(1);
			$result["text"] .= '/';
		}
		else { $_76 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unnary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_76 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_76 = \true; break;
	}
	while(\false);
	if($_76 === \true) { return $this->finalise($result); }
	if($_76 === \false) { return \false; }
}


/* Product: Unnary > ( Times | Div ) * */
protected $match_Product_typestack = ['Product'];
function match_Product($stack = []) {
	$matchrule = 'Product';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_87 = \null;
	do {
		$key = 'match_'.'Unnary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_87 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_86 = $result;
			$pos_86 = $this->pos;
			$_85 = \null;
			do {
				$_83 = \null;
				do {
					$res_80 = $result;
					$pos_80 = $this->pos;
					$key = 'match_'.'Times'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_83 = \true; break;
					}
					$result = $res_80;
					$this->setPos($pos_80);
					$key = 'match_'.'Div'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_83 = \true; break;
					}
					$result = $res_80;
					$this->setPos($pos_80);
					$_83 = \false; break;
				}
				while(\false);
				if($_83 === \false) { $_85 = \false; break; }
				$_85 = \true; break;
			}
			while(\false);
			if($_85 === \false) {
				$result = $res_86;
				$this->setPos($pos_86);
				unset($res_86, $pos_86);
				break;
			}
		}
		$_87 = \true; break;
	}
	while(\false);
	if($_87 === \true) { return $this->finalise($result); }
	if($_87 === \false) { return \false; }
}

public function Product_Unnary ( &$result, $sub ) {
        $result['val'] = $sub['val'];
    }

public function Product_Times ( &$result, $sub ) {
        $result['val'] *= $sub['operand']['val'];
    }

public function Product_Div ( &$result, $sub ) {
        $result['val'] /= $sub['operand']['val'];
    }

/* Plus: '+' > operand:Product > */
protected $match_Plus_typestack = ['Plus'];
function match_Plus($stack = []) {
	$matchrule = 'Plus';
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
		$key = 'match_'.'Product'; $pos = $this->pos;
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


/* Minus: '-' > operand:Product > */
protected $match_Minus_typestack = ['Minus'];
function match_Minus($stack = []) {
	$matchrule = 'Minus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_99 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_99 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_99 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_99 = \true; break;
	}
	while(\false);
	if($_99 === \true) { return $this->finalise($result); }
	if($_99 === \false) { return \false; }
}


/* Sum: Product > ( Plus | Minus ) * */
protected $match_Sum_typestack = ['Sum'];
function match_Sum($stack = []) {
	$matchrule = 'Sum';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_110 = \null;
	do {
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_110 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_109 = $result;
			$pos_109 = $this->pos;
			$_108 = \null;
			do {
				$_106 = \null;
				do {
					$res_103 = $result;
					$pos_103 = $this->pos;
					$key = 'match_'.'Plus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_106 = \true; break;
					}
					$result = $res_103;
					$this->setPos($pos_103);
					$key = 'match_'.'Minus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_106 = \true; break;
					}
					$result = $res_103;
					$this->setPos($pos_103);
					$_106 = \false; break;
				}
				while(\false);
				if($_106 === \false) { $_108 = \false; break; }
				$_108 = \true; break;
			}
			while(\false);
			if($_108 === \false) {
				$result = $res_109;
				$this->setPos($pos_109);
				unset($res_109, $pos_109);
				break;
			}
		}
		$_110 = \true; break;
	}
	while(\false);
	if($_110 === \true) { return $this->finalise($result); }
	if($_110 === \false) { return \false; }
}

public function Sum_Product ( &$result, $sub ) {
        $result['val'] = $sub['val'];
    }

public function Sum_Plus ( &$result, $sub ) {
        $result['val'] += $sub['operand']['val'];
    }

public function Sum_Minus ( &$result, $sub ) {
        $result['val'] -= $sub['operand']['val'];
    }

/* Variable: Name > "=" > Expr */
protected $match_Variable_typestack = ['Variable'];
function match_Variable($stack = []) {
	$matchrule = 'Variable';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_117 = \null;
	do {
		$key = 'match_'.'Name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_117 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '=') {
			$this->addPos(1);
			$result["text"] .= '=';
		}
		else { $_117 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_117 = \false; break; }
		$_117 = \true; break;
	}
	while(\false);
	if($_117 === \true) { return $this->finalise($result); }
	if($_117 === \false) { return \false; }
}

public function Variable_Name (&$result, $sub) {
        $result['val'] = ["name" => $sub['text']];
    }

public function Variable_Expr ( &$result, $sub ) {
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

public function Expr_Sum ( &$result, $sub ) {
        $result['val'] = $sub['val'];
    }

/* Start: Variable | Expr */
protected $match_Start_typestack = ['Start'];
function match_Start($stack = []) {
	$matchrule = 'Start';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_123 = \null;
	do {
		$res_120 = $result;
		$pos_120 = $this->pos;
		$key = 'match_'.'Variable'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_123 = \true; break;
		}
		$result = $res_120;
		$this->setPos($pos_120);
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_123 = \true; break;
		}
		$result = $res_120;
		$this->setPos($pos_120);
		$_123 = \false; break;
	}
	while(\false);
	if($_123 === \true) { return $this->finalise($result); }
	if($_123 === \false) { return \false; }
}

public function Start_Variable (&$result, $sub) {
        $name = $sub['val']['name'];
        $value = $sub['val']['value'];
        if (array_key_exists($name, $this->constants)) {
             throw new \Exception("Can't assign value to constant '$name'");
        }
        $this->variables[$name] = $value;
        $result['val'] = true;
    }

public function Start_Expr ( &$result, $sub ) {
        $result['val'] = $sub['val'];
    }



}

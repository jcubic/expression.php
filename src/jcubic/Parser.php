<?php
namespace jcubic;

use hafriedlander\Peg;

class Parser extends Peg\Parser\Basic {
  public $variables;

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
        if (!array_key_exists($name, $this->variables)) {
            throw new \Exception("variable $name not found");
        }
        $result['val'] = $this->variables[$name];
    }

/* Negation: '-' > operand:Value > */
protected $match_Negation_typestack = ['Negation'];
function match_Negation($stack = []) {
	$matchrule = 'Negation';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_28 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_28 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_28 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_28 = \true; break;
	}
	while(\false);
	if($_28 === \true) { return $this->finalise($result); }
	if($_28 === \false) { return \false; }
}


/* ToInt: '+' > operand:Value > */
protected $match_ToInt_typestack = ['ToInt'];
function match_ToInt($stack = []) {
	$matchrule = 'ToInt';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_34 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_34 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Value'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_34 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_34 = \true; break;
	}
	while(\false);
	if($_34 === \true) { return $this->finalise($result); }
	if($_34 === \false) { return \false; }
}


/* Unnary: (Negation | ToInt | Value) */
protected $match_Unnary_typestack = ['Unnary'];
function match_Unnary($stack = []) {
	$matchrule = 'Unnary';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_45 = \null;
	do {
		$_43 = \null;
		do {
			$res_36 = $result;
			$pos_36 = $this->pos;
			$key = 'match_'.'Negation'; $pos = $this->pos;
			$subres = $this->packhas($key, $pos)
				? $this->packread($key, $pos)
				: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
			if ($subres !== \false) {
				$this->store($result, $subres);
				$_43 = \true; break;
			}
			$result = $res_36;
			$this->setPos($pos_36);
			$_41 = \null;
			do {
				$res_38 = $result;
				$pos_38 = $this->pos;
				$key = 'match_'.'ToInt'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_41 = \true; break;
				}
				$result = $res_38;
				$this->setPos($pos_38);
				$key = 'match_'.'Value'; $pos = $this->pos;
				$subres = $this->packhas($key, $pos)
					? $this->packread($key, $pos)
					: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
				if ($subres !== \false) {
					$this->store($result, $subres);
					$_41 = \true; break;
				}
				$result = $res_38;
				$this->setPos($pos_38);
				$_41 = \false; break;
			}
			while(\false);
			if($_41 === \true) { $_43 = \true; break; }
			$result = $res_36;
			$this->setPos($pos_36);
			$_43 = \false; break;
		}
		while(\false);
		if($_43 === \false) { $_45 = \false; break; }
		$_45 = \true; break;
	}
	while(\false);
	if($_45 === \true) { return $this->finalise($result); }
	if($_45 === \false) { return \false; }
}

public function Unnary_ToInt ( &$result, $sub ) {
        $val = $sub['operand']['val'];
        if (is_string($val)) {
            $val = floatval($val);
        }
        $result['val'] = $val;
   }

public function Unnary_Value ( &$result, $sub ) {
        $result['val'] = $sub['val'];
    }

public function Unnary_Negation ( &$result, $sub ) {
        $result['val'] = $sub['operand']['val'] * -1;
    }

/* Times: '*' > operand:Unnary > */
protected $match_Times_typestack = ['Times'];
function match_Times($stack = []) {
	$matchrule = 'Times';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_51 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '*') {
			$this->addPos(1);
			$result["text"] .= '*';
		}
		else { $_51 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unnary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_51 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_51 = \true; break;
	}
	while(\false);
	if($_51 === \true) { return $this->finalise($result); }
	if($_51 === \false) { return \false; }
}


/* Div: '/' > operand:Unnary > */
protected $match_Div_typestack = ['Div'];
function match_Div($stack = []) {
	$matchrule = 'Div';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_57 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '/') {
			$this->addPos(1);
			$result["text"] .= '/';
		}
		else { $_57 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Unnary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_57 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_57 = \true; break;
	}
	while(\false);
	if($_57 === \true) { return $this->finalise($result); }
	if($_57 === \false) { return \false; }
}


/* Product: Unnary > ( Times | Div ) * */
protected $match_Product_typestack = ['Product'];
function match_Product($stack = []) {
	$matchrule = 'Product';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_68 = \null;
	do {
		$key = 'match_'.'Unnary'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_68 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_67 = $result;
			$pos_67 = $this->pos;
			$_66 = \null;
			do {
				$_64 = \null;
				do {
					$res_61 = $result;
					$pos_61 = $this->pos;
					$key = 'match_'.'Times'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_64 = \true; break;
					}
					$result = $res_61;
					$this->setPos($pos_61);
					$key = 'match_'.'Div'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_64 = \true; break;
					}
					$result = $res_61;
					$this->setPos($pos_61);
					$_64 = \false; break;
				}
				while(\false);
				if($_64 === \false) { $_66 = \false; break; }
				$_66 = \true; break;
			}
			while(\false);
			if($_66 === \false) {
				$result = $res_67;
				$this->setPos($pos_67);
				unset($res_67, $pos_67);
				break;
			}
		}
		$_68 = \true; break;
	}
	while(\false);
	if($_68 === \true) { return $this->finalise($result); }
	if($_68 === \false) { return \false; }
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
	$_74 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '+') {
			$this->addPos(1);
			$result["text"] .= '+';
		}
		else { $_74 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_74 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_74 = \true; break;
	}
	while(\false);
	if($_74 === \true) { return $this->finalise($result); }
	if($_74 === \false) { return \false; }
}


/* Minus: '-' > operand:Product > */
protected $match_Minus_typestack = ['Minus'];
function match_Minus($stack = []) {
	$matchrule = 'Minus';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_80 = \null;
	do {
		if (\substr($this->string, $this->pos, 1) === '-') {
			$this->addPos(1);
			$result["text"] .= '-';
		}
		else { $_80 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres, "operand");
		}
		else { $_80 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$_80 = \true; break;
	}
	while(\false);
	if($_80 === \true) { return $this->finalise($result); }
	if($_80 === \false) { return \false; }
}


/* Sum: Product > ( Plus | Minus ) * */
protected $match_Sum_typestack = ['Sum'];
function match_Sum($stack = []) {
	$matchrule = 'Sum';
	$this->currentRule = $matchrule;
	$result = $this->construct($matchrule, $matchrule);
	$_91 = \null;
	do {
		$key = 'match_'.'Product'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_91 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		while (\true) {
			$res_90 = $result;
			$pos_90 = $this->pos;
			$_89 = \null;
			do {
				$_87 = \null;
				do {
					$res_84 = $result;
					$pos_84 = $this->pos;
					$key = 'match_'.'Plus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_87 = \true; break;
					}
					$result = $res_84;
					$this->setPos($pos_84);
					$key = 'match_'.'Minus'; $pos = $this->pos;
					$subres = $this->packhas($key, $pos)
						? $this->packread($key, $pos)
						: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
					if ($subres !== \false) {
						$this->store($result, $subres);
						$_87 = \true; break;
					}
					$result = $res_84;
					$this->setPos($pos_84);
					$_87 = \false; break;
				}
				while(\false);
				if($_87 === \false) { $_89 = \false; break; }
				$_89 = \true; break;
			}
			while(\false);
			if($_89 === \false) {
				$result = $res_90;
				$this->setPos($pos_90);
				unset($res_90, $pos_90);
				break;
			}
		}
		$_91 = \true; break;
	}
	while(\false);
	if($_91 === \true) { return $this->finalise($result); }
	if($_91 === \false) { return \false; }
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
	$_98 = \null;
	do {
		$key = 'match_'.'Name'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_98 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		if (\substr($this->string, $this->pos, 1) === '=') {
			$this->addPos(1);
			$result["text"] .= '=';
		}
		else { $_98 = \false; break; }
		if (($subres = $this->whitespace()) !== \false) { $result["text"] .= $subres; }
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) { $this->store($result, $subres); }
		else { $_98 = \false; break; }
		$_98 = \true; break;
	}
	while(\false);
	if($_98 === \true) { return $this->finalise($result); }
	if($_98 === \false) { return \false; }
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
	$_104 = \null;
	do {
		$res_101 = $result;
		$pos_101 = $this->pos;
		$key = 'match_'.'Variable'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_104 = \true; break;
		}
		$result = $res_101;
		$this->setPos($pos_101);
		$key = 'match_'.'Expr'; $pos = $this->pos;
		$subres = $this->packhas($key, $pos)
			? $this->packread($key, $pos)
			: $this->packwrite($key, $pos, $this->{$key}(\array_merge($stack, [$result])));
		if ($subres !== \false) {
			$this->store($result, $subres);
			$_104 = \true; break;
		}
		$result = $res_101;
		$this->setPos($pos_101);
		$_104 = \false; break;
	}
	while(\false);
	if($_104 === \true) { return $this->finalise($result); }
	if($_104 === \false) { return \false; }
}

public function Start_Variable (&$result, $sub) {
        $name = $sub['val']['name'];
        $value = $sub['val']['value'];
        $this->variables[$name] = $value;
        $result['val'] = true;
    }

public function Start_Expr ( &$result, $sub ) {
        $result['val'] = $sub['val'];
    }



}

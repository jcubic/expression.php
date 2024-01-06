<h1 align="center">
  <img src="https://github.com/jcubic/expression.php/blob/parser-generator/.github/logo.svg?raw=true"
       alt="Expression.php - safely evaluate math, string, and boolean expressions" />
</h1>

[Safely evaluate math, string, and boolean expressions](https://github.com/jcubic/expression.php/)

[![Latest Stable Version](https://poser.pugx.org/jcubic/expression/v/stable.svg)](https://packagist.org/packages/jcubic/expression)
[![CI](https://github.com/jcubic/expression.php/actions/workflows/test.yaml/badge.svg)](https://github.com/jcubic/expression.php/actions/workflows/test.yaml)
[![Total Downloads](https://poser.pugx.org/jcubic/expression/downloads.svg)](https://packagist.org/packages/jcubic/expression)
[![License](https://poser.pugx.org/jcubic/expression/license.svg)](https://packagist.org/packages/jcubic/expression)

## DESCRIPTION

Use the Expression class when you want to evaluate mathematical or boolean expressions from
untrusted sources. You can define your own variables and functions, which are stored in the object.

## FEATURES
* Integers and floats
* Math Expressions: `*` `/` `-` `+` `%`, `^`, and `**`
* Boolean Expression: `&&`, `||`, and `!`
* Comparisons: `>` `<` `==` `!=` `<=` `>=`
* Strict comparison `===` and `!==`
* `pi` and `e` constants
* Regular Expressions and match operator `=~`
* String literals
* JSON objects and Arrays
* Square brackets operation on objects and arrays
* Bit shift operators `>>` `<<`
* Equal operator works on arrays and objects
* Functions and variables


## INSTALLATION

```bash
composer require jcubic/expression
```

## USAGE
```php
<?

require_once(__DIR__ . "/vendor/autoload.php");
use jcubic\Expression;

$e = new Expression();
// basic evaluation:
$result = $e->evaluate('2+2');
// supports: order of operation; parentheses; negation; built-in functions
$result = $e->evaluate('-8(5/2)^2*(1-sqrt(4))-8');
// support of booleans
$result = $e->evaluate('10 < 20 || 20 > 30 && 10 == 10');
// support for strings and match (regexes can be like in php or like in javascript)
$result = $e->evaluate('"Foo,Bar" =~ /^([fo]+),(bar)$/i');
// previous call will create $0 for whole match match and $1,$2 for groups
$result = $e->evaluate('$2');
// create your own variables
$e->evaluate('a = e^(ln(pi))');
// or functions
$e->evaluate('f(x,y) = x^2 + y^2 - 2x*y + 1');
// and then use them
$result = $e->evaluate('3*f(42,a)');
// create external functions
$e->functions['foo'] = function() {
  return "foo";
};
// and use them
$result = $e->evaluate('foo()');
```

## METHODS

* `$e->evalute($expr)`

Evaluates the expression and returns the result.  If an error occurs,
prints a warning and returns false.  If $expr is a function assignment,
returns true on success.

* `$e->e($expr)`

A synonym for $e->evaluate().

* `$e->vars()`

Returns an associative array of all user-defined variables and values.

* `$e->funcs()`

Returns an array of all user-defined functions.

## PARAMETERS
* `$e->suppress_errors`

Set to true to turn off warnings when evaluating expressions

* `$e->last_error`

If the last evaluation failed, contains a string describing the error.
(Useful when suppress_errors is on).

* `$e->functions`

Assoc array that contains functions defined externally.

* `$e->variables`

Assoc array that contains variables defined by user and externally.

## HISTORY
This project started as a fork. Original code was created by
[Miles Kaufmann ](http://www.phpclasses.org/browse/file/11680.html) and published
on PHPClasses.org. I've added a lot of features and bug fixes to original code,
but then decided that the code is really hard to modify to add new features and
fix bugs. So I decide to rewrite everything from scratch using
[PEG](https://en.wikipedia.org/wiki/Parsing_expression_grammar) parser generator.

The original code is still available as version 1.0 on
[packagist](https://packagist.org/packages/jcubic/expression#1.0.2) and
the source code you can find in
[legacy branch](https://github.com/jcubic/expression.php/tree/legacy).

## ACKNOWLEDGMENTS
* Logo uses:
  * icon from [Clarity icon set](https://github.com/jcubic/Clarity)
  * [Prosto one font](https://www.1001fonts.com/prosto-one-font.html) by [Pavel Emelyanov](https://www.behance.net/Levap)
* The public API is taken from the original code by Miles Kaufmann

## LICENSE
Copyright (c) 2024 [Jakub T. Jankiewicz](https://jakub.jankiewicz.org)<br/>
Released under [MIT](http://opensource.org/licenses/MIT) license

## P3\Db\Sql\ElementInterface

A sql element represents full sql statements or just part of it such as identifiers,
aliases, predicate, clauses, etc...

It provides a `getSQL(DriverInterface $driver = null, Params $params = null)` method
that returns the compiled SQL-string for the elements itself with the help of the given
driver and collects parameter values and types to be used when the sql-statements
are being prepared to be sent to the database server.

Sql elements can also be organized in hierarchies (`ElementInterface::setParent()`),
but an element can have only one parent. Changes to an inner element must invalidate
any compiled sql-string that has been cached.

### P3\Db\Sql
The `Db\Sql` class offers constants for  common SQL keywords and static factory methods
for creating complex or simple sql elements:

```php
use P3\Db\Sql;
use P3\Db\Sql\Alias;
use P3\Db\Sql\Expression;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Identifier;
use P3\Db\Sql\Statement;

// Create Identifier elements: dots are considered identifier separators
$column = Sql::identifier('category_id'); // sql-string: "category_id"
$column = Sql::identifier('p.category_id'); // sql-string: "p"."category_id"

// Create sql Alias elements: dots are considered part of the alias expression
$alias = Sql::alias('t0'); // sql-string: "t0"
$alias = Sql::alias('my.Alias'); // sql-string: "my.Alias"

// Create parametric sql Expression elements:
// substitution parameter markers must be enclosed in curly brackets
$expr = Sql::expression('(price * {vat_rate})', [
    'vat_rate' => 20.0,
]); // sql-string: (price * :expr1)
// Using shorter method name `expr`
// sql-string: CONCAT(:expr1, ' ', "surname")
$expr = Sql::expr('CONCAT({title}, ' ', "surname")', ['title' => 'sir']);

// Create parameter-less sql Literal expression elements:
// substitution parameter markers must be enclosed in curly brackets
$literal = Sql::literal('("price" * 20.0)'); // sql-string: (price * 20.0)

$select = Sql::select(); // returns a Statement\Select instance
$insert = Sql::insert(); // returns a Statement\Insert instance
$update = Sql::update(); // returns a Statement\Update instance
$delete = Sql::delete(); // returns a Statement\Delete instance
```

All the factory methods above can be replaced with constructor calls with the same signature.

### Factory functions

To make code more coincise a few importable functions are provided:

```php
use function P3\Db\Sql\alias as ali;
use function P3\Db\Sql\expression as exp;
use function P3\Db\Sql\identifier as idn;
use function P3\Db\Sql\literal as lit;

$column  = idn('p.category_id');
$alias   = ali('t0');
$expr    = exp('(price * {vat_rate})', ['vat_rate' => 20.0]);
$literal = lit('("price" * 20.0)');
```
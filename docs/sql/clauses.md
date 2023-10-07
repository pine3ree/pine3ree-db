# Clauses

The abstract class `pine3ree\Db\Sql\Clause` is the base class for implementations
that abstract common SQL clauses such as the `JOIN` clause and the conditional
clauses `WHERE`, `HAVING` and `ON`.

## The Where, Having and On conditional clauses

A conditional clause wraps a search condition object as an instance of `Predicate\Set`
It also provides proxy methods to all the condition building methods of a predicate-set.
After the first call of any of such methods we are brought into the context of the
composed predicate-set.

```php

use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Predicate;

ConditionalClause::getSearchCondition(): Predicate\Set;
ConditionalClause::isEmpty(): bool
ConditionalClause::hasParams(): bool
ConditionalClause::getParams(): ?Params
ConditionalClause::getSQL(DriverInterface $driver = null, Params $params = null): string
/**
 * @param Predicate|string|array $predicate A Predicate|Predicate\Set instance
 *      or a specs-array [identifier, operator, value] or [identifier => value]
 */
ConditionalClause::addPredicate($predicate): Predicate\Set;
ConditionalClause::literal(string $literal): Predicate\Set;
ConditionalClause::expression(string $expression, array $substitutions = []): Predicate\Set;
ConditionalClause::expr(string $expression, array $substitutions = []): Predicate\Set;
ConditionalClause::all($identifier, string $operator, Select $select): Predicate\Set;
ConditionalClause::any($identifier, string $operator, Select $select): Predicate\Set;
ConditionalClause::some($identifier, string $operator, Select $select): Predicate\Set;
ConditionalClause::between($identifier, $min_value, $max_value): Predicate\Set;
ConditionalClause::notBetween($identifier, $min_value, $max_value): Predicate\Set;
ConditionalClause::exists(Select $select): Predicate\Set;
ConditionalClause::notExists(Select $select): Predicate\Set;
ConditionalClause::in($identifier, $valueList): Predicate\Set;
ConditionalClause::notIn($identifier, $valueList): Predicate\Set;
ConditionalClause::is($identifier, $value): Predicate\Set;
ConditionalClause::isNot($identifier, $value): Predicate\Set;
ConditionalClause::isNull($identifier): Predicate\Set;
ConditionalClause::isNotNull($identifier): Predicate\Set;
ConditionalClause::isTrue($identifier): Predicate\Set;
ConditionalClause::isFalse($identifier): Predicate\Set;
ConditionalClause::isUnknown($identifier): Predicate\Set;
ConditionalClause::isNotUnknown($identifier): Predicate\Set;
ConditionalClause::like($identifier, $pattern, string $escape = null): Predicate\Set;
ConditionalClause::notLike($identifier, $pattern, string $escape = null): Predicate\Set;
ConditionalClause::equal($identifier, $value): Predicate\Set;
ConditionalClause::eq($identifier, $value): Predicate\Set;
ConditionalClause::notEqual($identifier, $value): Predicate\Set;
ConditionalClause::neq($identifier, $value): Predicate\Set;
ConditionalClause::ne($identifier, $value): Predicate\Set;
ConditionalClause::lessThan($identifier, $value): Predicate\Set;
ConditionalClause::lt($identifier, $value): Predicate\Set;
ConditionalClause::lessThanEqual($identifier, $value): Predicate\Set;
ConditionalClause::lte($identifier, $value): Predicate\Set;
ConditionalClause::greaterThanEqual($identifier, $value): Predicate\Set;
ConditionalClause::gte($identifier, $value): Predicate\Set;
ConditionalClause::greaterThan($identifier, $value): Predicate\Set;
ConditionalClause::gt($identifier, $value): Predicate\Set;
ConditionalClause::and(): Predicate\Set;
ConditionalClause::or(): Predicate\Set;
ConditionalClause::beginGroup(string $defaultLogicalOperator = Sql::AND): Predicate\Set;

```

An endGroup() method is not provided as we call it from the search-condition `Predicate\Set` context

Examples:

```php

use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Clause\Where;
use pine3ree\Db\Sql\Predicate;
use pine3ree\Db\Sql\Statement\Select;

$date = '2023-10-07';

$select = Sql::select();
$select
    ->from('tax_rate', 'tr')
    ->where // changed context to the composed Where instance
        ->lte('tr.date_min', $date) // changed context to the composed Predicate\Set instance
        ->and()
        ->beginGroup() // changed context to the nested Predicate\Set instance
            ->equal('tr.date_max', '0000-00-00')
            ->or()
            ->gte('tr.date_max', $date)
        ->endGroup(); // changed context back to the Where::$searchCondition Predicate\Set instance

// SELECT "tr".* FROM "tax_rate" "tr" WHERE "tr"."date_min" <= :lte1 AND ("tr"."date_max" = :eq1 OR "tr"."date_max" >= :gte1)

$select = Sql::select();
$select
    ->columns([
        'id',
        'name',
        'originalPrice' => 'price',
    ])
    ->column(Sql::literal('(p.price - p.discount)'), 'discountedPrice')
    ->from('product', 'p')
    ->where
        ->gt('discount', 0.0)
    ->top() // Back to the Select instance, we could have called ->up()->up(), or ->closest(Select::class)
    ->having
        ->lte('discountedPrice', 100.00);

// SELECT "p"."id", "p"."name", "p"."price" AS "originalPrice", ("p".price - "p".discount) AS "discountedPrice"
// FROM "product" "p"
// WHERE "discount" > :gt1
// HAVING "discountedPrice" <= :lte1
```

## The Join clause

The class `pine3ree\Db\Sql\Clause\Join` abstract the SQL JOIN clause.
A Join instance is created with at least 2 parameters:

- the join type ('', 'INNER', 'CROSS', 'LEFT', 'RIGHT', 'STRAIGHT', 'NATURAL',
  'NATURAL LEFT', 'NATURAL RIGHT' - `Sql::JOIN_*` constants ara available)
- the joined table name

and most commonly with the following optional parameters

- the joined table alias
- the join specification in the form of a sql literal predicate rendered as the 
  wrapped string, a sql identifier that is automatically wrapped in a
  `USING("identifier")` clause, an `On` clause or conditions in various form
  (strings, arrays, predicates, predicate-sets, ..) the will be used to build
  an `On` conditional-clause instance

Examples:

```php

use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Clause\Join;
use pine3ree\Db\Sql\Predicate;
use pine3ree\Db\Sql\Statement\Select;

$select = new Select(); // or $select = Sql::select()

$select
    ->columns([
        'title',
        'summary',
        'date',
        'author' => 'u.name', // key:alias, value: column
    ])
    ->from('post', 'p')
    ->addJoin(new Join(
        Sql::JOIN_LEFT, // or just "LEFT",
        'user',
        'u',
        'u.id = p.user_id' // Will be used as a literal predicate for the On clause
    ));

// The resulting sql-string is split into two lines to improve readability
//
// SELECT "p"."title", "p"."summary", "p"."date", "u"."name" AS "author" FROM "post" "p"
// LEFT JOIN "user" "u" ON ("u".id = "p".user_id)

// If using a literal predicate then "ON" sql keyword must be included manually, i.e:
new Predicate\Literal('ON u.id = p.user_id')
```

You will usually call join select method intead of programmatically creating new
`Join instances`

```php

use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Clause\Join;
use pine3ree\Db\Sql\Statement\Select;

$select = Sql::select();

$select
    ->columns([
        '*',
        'author' => 'u.name',
    ])
    ->from('post', 'p')
    ->leftJoin('user', 'u', [ // conditions array used to build the On clause
        'u.id = p.user_id', // literal string
        'u.enabled' => true, // equality condition in key => value form
    ]);

// SELECT "p".*, "u"."name" AS "user" FROM "post" "p"
// LEFT JOIN "user" "u" ON ("u".id = "p".user_id AND "u"."enabled" = :eq1)
```

The sql `Select` statement class provides the following utility methods for sql-joins:

```php
Select::addJoin(Join $join): self;

/**
 * Common signature
 *
 * @param string $table The joined table name
 * @param string $alias The joined table alias
 * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
 * @return $this Fluent interface
 */

Select::innerJoin(string $table, string $alias, $specification = null): self;
Select::leftJoin(string $table, string $alias, $specification = null): self;
Select::rightJoin(string $table, string $alias, $specification = null): self;
Select::naturalJoin(string $table, string $alias, $specification = null): self;
Select::naturalLeftJoin(string $table, string $alias, $specification = null): self;
Select::naturalRightJoin(string $table, string $alias, $specification = null): self;
Select::crossJoin(string $table, string $alias, $specification = null): self;
Select::straightJoin(string $table, string $alias, $specification = null): self;

```
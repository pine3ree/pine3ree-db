## pine3ree\Db\Sql\Predicate and Predicate\Set

SQL predicates are parts of an sql-statement normally abstracting search-conditions
inside sql clauses like WHERE, HAVING, ON. They usually resolve to a sql boolean value.

They can be part of a bigger set (predicate-set) and combined together either with
an `AND` sql logical operator or with an `OR` sql logical operator. The default
predicate combination of a set can be decided when calling its constructor. The default
combination is `AND`.

A predicate-set may also be part of a bigger enclosing set. In this case the enclosed
set is evaluated first and the result is combined with the other top level predicates.
In a compiled sql-statement inner predicate sets are rendered enclosed in parenthesis.

The predicate-set abstraction also provides chainable factory methods for creating
and adding single predicates and inner sets to itself.
These methods are proxied by conditional clause classes that composes a predicate-set
as their search-condition.
The default logical operator is used unless the factory method is preceeded by either
an `Predicate\Set::and()` or a `Predicate\Set::or()` chainable method call.

During sql compilation predicate identifiers are quoted as sql-identifiers. To make
them to be quoted as aliases you must provide Alias instances instead of strings.

Examples:

```php
use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Predicate;
use pine3ree\Db\Sql\Statement\Select;
use function pine3ree\Db\Sql\alias;

// Empty predicate-set with "AND" as default logical operator
$predicateSet = new Predicate\Set();
// Add Predicate\Comparison predicates with equality operator
$predicateSet->equal('p.vat_rate', 20.0); // "p"."vat_rate" = :eq1
$predicateSet->eq(alias('tot.Price'), 20.0); // AND "tot.Price" = :eq2

$predicateSet = new Predicate\Set([], Sql::OR); // default logical operator is "OR"
$predicateSet->lessThan('vat_rate', 20.0);// "vat_rate" < :lt1
$predicateSet->lt('price', 100.0); // OR "price" < :lt2
// Add a Predicate\Literal predicate with an expression used as it is
$predicateSet->and()->literal('"published" IS TRUE'); // AND "published" IS TRUE
$predicateSet->or()->gt('stock', 10); // OR "stock" > :gt1
```

As a convenience predicate-set methods may also have a shorter and/or equivalent form:

```php
use pine3ree\Db\Sql\Predicate;

// Creates a Predicate\Comparison with operator =
Predicate\Set::equal($identifier, $value);
Predicate\Set::eq($identifier, $value);
// Creates a Predicate\Comparison with operator !=
Predicate\Set::notEqual($identifier, $value);
Predicate\Set::neq($identifier, $value);
// Creates a Predicate\Comparison with operator <>
Predicate\Set::ne($identifier, $value);
// Creates a Predicate\Comparison with operator <
Predicate\Set::lessThan($identifier, $value);
Predicate\Set::lt($identifier, $value);
// Creates a Predicate\Comparison with operator <=
Predicate\Set::lessThanEqual($identifier, $value);
Predicate\Set::lte($identifier, $value);
// Creates a Predicate\Comparison with operator >=
Predicate\Set::greaterThanEqual($identifier, $value);
Predicate\Set::gte($identifier, $value);
// Creates a Predicate\Comparison with operator >
Predicate\Set::greaterThan($identifier, $value);
Predicate\Set::gt($identifier, $value);

Predicate\Set::like($identifier, $value, $escape); // Predicate\Like
Predicate\Set::notLike($identifier, $value, $escape); // Predicate\NotLike

Predicate\Set::between($identifier, $min, $max); // Predicate\Between
Predicate\Set::notBetween($identifier, $min, $max); // Predicate\NotBetween

Predicate\Set::in($identifier, array|Select $valueList); // Predicate\In
Predicate\Set::notIn($identifier, array|Select $valueList); // Predicate\NotIn

Predicate\Set::is($identifier, true|false|null|'UNKNOWN'); // Predicate\Is
Predicate\Set::isNot($identifier, true|false|null|'UNKNOWN'); // Predicate\IsNot
Predicate\Set::isNull($identifier); // Predicate\IsNull
Predicate\Set::isNotNull($identifier); // Predicate\IsNotNull
Predicate\Set::isTrue($identifier); // Predicate\IsTrue
Predicate\Set::isFalse($identifier); // Predicate\IsFalse
Predicate\Set::isUnknown($identifier); // Predicate\IsUnknown

Predicate\Set::literal(string $literal); // Predicate\Literal
Predicate\Set::expression(string $expr, array $susbtitutions); // Predicate\Expression
Predicate\Set::expr(string $expr, array $susbtitutions); // Predicate\Expression

Predicate\Set::exists($identifier, $operator, Select $select); // Predicate\Exists
Predicate\Set::notExists($identifier, $operator, Select $select); // Predicate\NotExists

Predicate\Set::all($identifier, $operator, Select $select); // Predicate\All
Predicate\Set::any($identifier, $operator, Select $select); // Predicate\Any
Predicate\Set::some($identifier, $operator, Select $select); // Predicate\Some
```

Predicate sets initialized with a string will use the string to create a literal predicate:

```php
use pine3ree\Db\Sql\Predicate;

// The following set will contain 1 predicate of class Predicate\Literal
$predicateSet = new Predicate\Set('MAX("price") <= 100.0'); // MAX("price") <= 100.0
```

Subsets of predicates may be created using `begingGroup()` calls:

```php
use pine3ree\Db\Sql\Predicate;

$predicateSet = new Predicate\Set();

// Add predicates

// Begin a sub set
// The following code will be compiled to ("price" > :gt1 OR "stock" > :gt2)
$predicateSet
    ->beginGroup() // entering the subset scope
        ->gt('price', 100.0)
        ->or()
        ->gt('stock', 42)
    ->endGroup() // back to the upper-level set scope
```

Predicate sets can also be created using array specifications. This is useful when
used in sql statement `where()`, `having()`, `on()` method calls.

Examples:

```php
use pine3ree\Db\Sql;

$conditions = [
    'id IS NOT NULL', // a string is converted to a literal predicate
    ['price', '<=', 100.0], // identifier, operator, value
    ['date_created', 'between', '2020-01-01', '2020-12-31'], // identifier, operator, value, extra-value
    ['name', 'LIKE', 'B%'], // using the 'LIKE' exact keyword
    ['name', 'like', 'B%'], // using the lowercase alias
    ['name', Sql::LIKE, 'B%'], // using the Sql::LIKE constant
    ['name', '~', 'B%'], // using the '~' alias
    ['name', 'NOT LIKE', 'A%'], // using the 'NOT LIKE' exact keywords
    ['name', Sql::NOT_LIKE, 'A%'], // using the Sql::NOT_LIKE constant
    ['name', 'notLike', 'A%'], // using the lowercase 'notLike' alias
    ['name', '!~', 'A%'], // using the '!~' alias
    ['category_id', 'in', [11, 22, 33]], // "category_id" IN (:in1, :in2, :in3)
    ['store_id', 'in', [1, 2, null]], // "store_id" IN (:in4, :in5) OR "store_id" IS NULL
    'vat_rate' => 10.0, // identifier => value implies the equality operator
    '||' => [ // creates a group with 'OR' as default logical operator
        // predicate-specs-1,
        // predicate-specs-2,
        //...
    ],
    '&&' => [ // creates a group with 'AND' as default logical operator
        // predicate-specs-1,
        // predicate-specs-2,
        //...,
    ],
 ];

Sql::select('*')->from('product')->where($conditions);
```

Predicate specifications may use the exact sql operator string either directly or
preferably via Sql class constants, or using camelCased versions such as "notLike".
"LIKE" and "NOT LIKE" can be specified using `~` and `!~` convenience aliases
respectively.

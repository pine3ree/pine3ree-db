# pine3ree\Db

## Quick start

```php
use pine3ree\Db;
use pine3ree\Db\Factory\DbFactory;
use PDO;

// 1. Create a dbal instance using an existing PDO connection
$pdo = new PDO('my-db-dsn', 'my-db-username', 'my-db-password');
$db = new Db($pdo);

// 2. Create a dbal instance using pdo configuration: the PDO connection is created on demand
$db = new Db('my-db-dsn', 'my-db-username', 'my-db-password');

// 3. Create a dbal instance using a factory: the provided factory fetch a configuration
// array from a psr-container under the `config` id/alias with specific database
// configuration subarray under either a `db` or `pdo` key.
$factory = new DbFactory();
$db = $factory($container);

// 4. Create a dbal instance using a factory method directly from database/pdo
// configuration array:
$db = DbFactory::create([
    'driver'   => 'mysql',
    'host'     => 'localhost',
    'port'     => 3306,
    'database' => 'testdb',
    'username' => 'testuser',
    'password' => 'secret',
    'charset'  => 'utf8',
]);

// Simple proxy method to \PDO::query() returning a traversable PDOStatement or
// false if query execution fails
$stmt = $db->query('SELECT * FROM product WHERE price < 100.0 AND id < 100');

// Simple proxy method to \PDO::exec(), returns the number of affected rows, or
// false if execution fails
$affected = $db->exec('UPDATE product SET published = FALSE WHERE stock <= 0');
```

Other examples:

```php
// Fetch all rows from the "product" table
// fetchAll(string $table, $where = null, $order = null, int $limit = null, int $offset = null): array
$products = $db->fetchAll('product');

// Fetch the product row with column id = 42
// fetchOneBy(string $table, string $column, $value, $order = null): ?array
$product = $db->fetchOneBy('product', 'id', 42);

// Same row using `fetchOne()` with condition in array-format
// fetchOne(string $table, $where = null, $order = null): ?array
$product = $db->fetchOne('product', ['id' => 42]);

$fiftyExpensiveProducts = $db->fetchAll(
    'product', [ // conditions array start
        ['price', '>', 1000.00], // 1 conditions in array-format
    ], // conditions array end
    [
        'price' => 'ASC',
    ],
    50
);

$tenMostExpensiveProducts = $db->fetchAll('product', null, ['price' => 'DESC'], 10);

$mostExpensiveProduct = $db->fetchOne('product', null, ['price' => 'DESC']);
```

### Constructor arguments

`pine3ree\Db` supports the same constructor arguments as the `\PDO` class.

It also supports an extra argument, an optional custom PDO subclass to use in
lazy connection instances.

```php
class Db
{
   /**
     * @param string|PDO $dsn_or_pdo A valid pdo dsn string or an existing pdo connection instance
     * @param string|null $username PDO connection username
     * @param string|null $password PDO connection password
     * @param array|null $options PDO connection options
     * @param string|null $pdoClass An optional PDO subclass to use when creating a new connection
     */
    public function __construct(
        $dsn_or_pdo,
        string $username = null,
        string $password = null,
        array $options = null,
        string $pdoClass = null
    ) {
}
//...
```
The first argument can also be an existing PDO instance itself, that will be used
as the composed pdo connection.

### Factory configuration parameters

Factory configuration retrieved from the container should return an array like the
one below:

```php
// file config.php
return [
    // full dsn specification
    'db' => [ // alt key: 'pdo' => [...]
        'dns'      => 'mysql:dbname=testdb;host=localhost;port=3306;charset=utf8',
        'username' => 'testuser', // alt key: 'user'
        'password' => 'secret', // alt key: 'passwd' or 'pass'
    ],
    // ...or single parameters specs
    'db' => [
        'driver'   => 'mysql',
        'dbname'   => 'testdb', // alt key: 'database'
        'host'     => 'localhost', // alt key: 'hostname'
        'port'     => 3306,
        'charset'  => 'utf8',
        'username' => 'testuser', // alt key: 'user'
        'password' => 'secret', // alt key: 'passwd' or 'pass'
        'options'  => [
            // pdo-options array
        ]
    ],
];
```

The database configuration subkeys depend on the db driver used and must be all
in snake_case format. Please check the pdo driver page https://www.php.net/manual/en/pdo.drivers.php
for more information.

The factory will attempt to build a valid pdo DSN with the provided configuration parameters.

Supported drivers are `mysql`, `pgsql`, `sqlite`, `sqlsrv` and `oci`.

## CRUD commands

To start building a crud databse command you can use the following methods:

```php
$select = $db->select(); // returns a pine3ree\Db\Command\Select instance
$insert = $db->insert(); // returns a pine3ree\Db\Command\Insert instance
$update = $db->update(); // returns a pine3ree\Db\Command\Update instance
$delete = $db->delete(); // returns a pine3ree\Db\Command\Delete instance
```

Database command instances provide a fluent interface for building sql statement.
The sql build is actually perfomed by the composed sql-statement (`pine3ree\Db\Sql\Statement`)
instance with the help of the sql-driver (`pine3ree\Sql\DriverInterface`) created for
the current connection.

The corresponding sql-statement objects ca be created with the following `pine3ree\Db\Sql` helper
class static methods:

```php
$select = Sql::select(); // returns a pine3ree\Db\Sql\Statement\Select instance
$insert = Sql::insert(); // returns a pine3ree\Db\Sql\Statement\Insert instance
$update = Sql::update(); // returns a pine3ree\Db\Sql\Statement\Update instance
$delete = Sql::delete(); // returns a pine3ree\Db\Sql\Statement\Delete instance
```

The `Sql\Statement` classes, as any other `Sql\Element` class, provide a `getSQL()`
method which compiles the sql string for the given sql-driver argument or the default
`Ansi` driver. The sql-drivers provide identifier quoting and other sql transformations
according to the underlying platform. The `getSQL()` method also collects user-provided
parameter values along with their pdo-param types and sets named markers in their place
into the sql string. The paramater collector can be retrieved by `getParams()` either from
the sql-statement object or the wrapping command. A internal collector will be created
only if not passed-in as the 2nd argument of the `getSQL()` call.

All database command classes implement the `execute()` method.

- For writer-DML-commands (Insert|Update|Delete) `execute()` will call the writer
  method `Writer::exec()` and will return either the number of rows affected or
  `false` on failure.
- For reader-DQL-commands (Select) `execute()` will call the reader method
  `Reader::query()` and will return either a traversable `\PDOStatement`
  result-set object or `false` on failure.

Unless otherwise stated in all the examples' compiled sql-strings identifiers
and aliases will be quoted according to an implied `Ansi` driver, i.e. using
double quotes `"`.

### Db::select()

Create a `pine3ree\Db\Command\Select` reader command instance

```php
use pine3ree\Db;
use pine3ree\Db\Sql;

/** @var Db $db */

$select = $db->select(); // create a generic empty Select command instance

// SELECT * FROM "product"
$select = $db->select('*', 'product');
$select = $db->select('*')->from('product');
$select = $db->select(null, 'product');
$select = $db->select()->from('product');

// Use table alias: SELECT * FROM "product" "p"
$select = $db->select('*', 'product', 'p');
$select = $db->select('*')->from('product', 'p');
$select = $db->select()->from('product', 'p');

 // SELECT "p"."price", "p"."vat_rate" AS "vatRate" FROM "product" "p"
$select = $db->select(['price', 'vat_rate' => 'vatRate'])->from('product', 'p');

// Add where condition LessThanEqual and order-by clause
$select->where->lte('price', 1000.0); // WHERE "price" <= :lte1 (named parameter marker)

// ORDER BY "p"."price" ASC
$select->orderBy('p.price', 'ASC');
$select->orderBy('p.price', Sql::ASC);

// ORDER BY "price" ASC, "vat_rate" DESC
$select->orderBy([
    'price' => Sql::ASC, // or just 'price' => 'ASC'
    'vat_rate' => Sql::DESC, // or just 'vat_rate' => 'DESC'
]);

$stmt = $select->execute(); // or $select->query(), returns a \PDOStatement instance or FALSE

// SELECT
//    "category_id" AS "catId",
//    COUNT(*) AS "numProducts"
// FROM "product" WHERE "price" > :gt1
// GROUP BY "category_id"
// HAVING "numProducts" >= :gte1
$select = $db->select()
    ->column('category_id', 'catId')
    ->count('*', 'numProducts')
    ->from('product');
    ->where->gte('price', 10.0);
// using $select->where or $select->having changes the scope and the fluent interface
// method chain is broken

// Add a GROUP BY
// GROUP BY "category_id" HAVING "numProducts" < :lte1
$select->groupBy('category_id')
    ->having->lte('numProducts', 5);

// SELECT MIN("price") FROM "product" GROUP BY "category_id"
$select = $db->select()->min('price')->from('product')->groupBy('category_id');
```


### Db::insert()

Create and optionally execute an `pine3ree\Db\Command\Insert` writer command instance.

```php
// INSERT INTO "product" ("name", "price") VALUES (:val1, :val2)
$insert = $db->insert()
    ->into('product')
    ->row([
	    'name' => 'product-1',
    	'price' => 100.00,
	]);

// equivalent to
$insert = $db->insert()
    ->into('product')
    ->columns(['name', 'price'])
    ->values(['product-1', 100.00]);

$result = $insert->execute() // or $insert->exec(), returns TRUE or FALSE for single row insert
```

**Insert and execute** shortcut call, when both arguments (`$table` and `$row`/`$rows`)
are provided:

```php
$result = $db->insert('product', [
    'name' => 'product-111',
    'price' => 111.11,
]); // returns TRUE or FALSE for single insert

// get the last generated value if the insert is successful
$id = $result ? $db->lastInsertId() : null;
```

Insert many rows:

```php
// INSERT INTO "product" ("name", "price") VALUES (:val1, :val2), (:val3, :val4)
$num_inserted = $db->insert('product', [
    [
        'name' => 'product-111',
        'price' => 111.11,
    ],
    [
        'name' => 'product-222',
        'price' => 222.22,
    ],
]); // returns integer or FALSE for multi-rows inserts

// equivalent to
$num_inserted = $db->insert()
    ->into('product')
    ->rows([
        [
            'name' => 'product-111',
            'price' => 111.11,
        ],
        [
            'name' => 'product-222',
            'price' => 222.22,
        ],
    ])->execute(); // or exec()

// and to
$num_inserted = $db->insert()
    ->into('product')
    ->columns(['name', 'price'])
    ->values([
        'product-111',
        111.11,
    ])
    ->values([
        'product-222',
        222.22,
    ])->execute(); // or exec()

// and to
$num_inserted = $db->insert()
    ->into('product')
    ->columns(['name', 'price'])
    ->multipleValues([
        [
            'product-111',
            111.11,
        ],
        [
            'product-222',
            222.22,
        ],
    ])->execute();
```

By default `Insert::values(array $values, bool $reset = false)` and
`Insert::row(array $row, bool $reset = false)` will add new inset values to
existing ones. This can be changed by setting the 2nd
argument to `true`:

```php
$insert = $db->insert('product');

$insert->row(['price' => 111.11, 'stock' => 111]); // adds 1 set of values
$insert->row(['price' => 222.22, 'stock' => 222]); // adds 1 set of values
// columns "price" and "stock" are alredy specified by previuous row() calls
$insert->values([333.33, 333]); // adds 1 set of values

$insert->execute(); // this will try to insert 3 rows

$insert->values([444.44, 444]); // adds another set of values
$insert->execute(); // this will try to insert 4 rows

 // adds 1 set of values after removing the old ones
$insert->row(['price' => 555.55, 'stock' => 555], true);
$insert->execute(); // this will try to insert 1 row
```

The opposite happens for `Insert::rows(array $rows, bool $reset = true)` and
`Insert::multipleValues(array $values, bool $reset = true)`. These methods calls
will insert the exact rows/values provided unless the 2nd argument is set to `false`.


### Db::update()

The `pine3ree\Db\Command\Update` command abstracts an INSERT operation

A non empty condition/predicate is required, otherwise an exception is thrown.

Examples:

```php
// UPDATE "product" SET "published" = :set1 WHERE stock > 0
$update = $db->update()->table('product')->set('published', true)->where('stock > 0');
$update = $db->update('product')->set('published', true)->where('stock > 0');
$affected = $update->execute(); // or exec()

// Immediate command execution
// UPDATE "product" SET "published" = :set1 WHERE TRUE, we use the condition "TRUE" to update all records
$affected = $db->update('product', ['published' => true], 'TRUE');
```


### Db::delete()

The `pine3ree\Db\Command\Delete` command abstracts a SQL DELETE operation

A non empty condition/predicate is required, otherwise an exception is thrown.

Examples:
```php
// DELETE FROM "product" WHERE stock <= 0
$delete = $db->delete()->from('product')->where('stock <= 0');
$delete = $db->delete('product')->where('stock <= 0');
$num_deleted = $delete->execute(); // or exec()

// immediate command execution
// DELETE FROM "product" WHERE stock <= 0
$num_deleted = $db->delete('product', 'stock <= 0');
```


### Sql driver proxy helper methods

The following methods are simple proxies to methods implemented in the
`pine3ree\Db\Sql\DriverInterface` class of the current dbal's sql-driver instance.

- `Db::quoteIdentifier(string $identifier)` quotes given column/table SQL identifier
- `Db::quoteAlias(string $alias)` quotes given SQL aliase
- `Db::quoteValue(null|scalar $value)` perform type-casting and quotes - when required - the given value

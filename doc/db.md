# P3\Db

## Quick start

```php
use P3\Db;
use P3\Db\Factory\DbFactory;
use PDO;

// 1. using an existing PDO connection
$pdo = new PDO('my-db-dsn', 'my-db-username', 'my-db-password');
$db = new Db($pdo);

// 2. using pdo configuration directly: the PDO connection is created on demand
$db = new Db('my-db-dsn', 'my-db-username', 'my-db-password');

// 3. using a factory: the provided factory uses a psr-container and expects to find
// a configuration array under the `config` container id/alias with specific database
// configuration under either a `db` or `pdo` key.
$factory = new DbFactory();
$db = $factory($container);

// fetch all rows from the "product" table
$products = $db->fetchAll('product');

// fetch the product row with column id = 42
$product = $db->fetchOneBy('product', 'id', 42);

// same row using `fetchOne()` with condition
$product = $db->fetchOne('product', ['id' => 42]);

$fiftyExpensiveProducts = $db->fetchAll('product', [
    ['price', '>', 1000.00],
], ['price' => 'ASC'], 50);

$tenMostExpensiveProducts = $db->fetchAll('product', null, ['price' => 'DESC'], 10);

$mostExpensiveProduct = $db->fetchOne('product', null, ['price' => 'DESC']);

```

## Constructor arguments

`P3\Db` supports the same constructor arguments as the `\PDO` class.

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

## Factory configuration parameters

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
in snake case format. Please check the pdo driver page https://www.php.net/manual/en/pdo.drivers.php
for more information.

Supported drivers are `mysql`, `pgsql`, `sqlite`, `sqlsrv` and `oci`.

## CRUD commands

To start building a crud databse command you can use the following methods:

```php
$select = $db->select(); // returns a P3\Db\Command\Select instance
$insert = $db->insert(); // returns a P3\Db\Command\Insert instance
$update = $db->update(); // returns a P3\Db\Command\Update instance
$delete = $db->delete(); // returns a P3\Db\Command\Delete instance
```

Database command instances provide a fluent interface for building sql statement.
The sql build is actually perfomed by the composed sql-statement (`P3\Db\Sql\Statement`)
instance with the help of the sql-driver (`P3\Sql\DriverInterface`) created for
the current connection.

The corresponding sql-statement objects ca be created with the following `P3\Db\Sql` helper
class static methods:

```php
$select = Sql::select(); // returns a P3\Db\Sql\Statement\Select instance
$insert = Sql::insert(); // returns a P3\Db\Sql\Statement\Insert instance
$update = Sql::update(); // returns a P3\Db\Sql\Statement\Update instance
$delete = Sql::delete(); // returns a P3\Db\Sql\Statement\Delete instance
```

The `Sql\Statement` classes, as any other `Sql\Element` class, provide a `getSQL()`
method which compiles the sql string for the given sql-driver argument or the default
`Ansi` driver. The sql-drivers provide identifier quoting and other sql transformations
according to the underlying platform. The `getSQL()` method also collects user-provided
parameter values along with their pdo-param types and sets named markers in their place
into the sql string. The paramater collector can be retrieved by `getParams()` either from
the sql-statement object or the wrapping command. A internal collector will be created
only if not passed-in as the 2nd argument of the `getSQL()` call.

### Db::select()

Create a select command instance, which is a reader-command, whose method `execute()`
is forwarded to the reader-command method `query()`:

```php
//...
$select = $db->select(); // generic Select command

$select = $db->select('*', 'product', 'p');
// equivalent to
$select = $db->select('**')->from('product', 'p'); // SELECT * FROM "product" "p"

// add where condition and order-by clause
$select->where->lte('price', 1000.00); // WHERE "price" <= :lte1 (named parameter marker)
$select->orderBy('p.price', 'ASC'); // ORDER BY "price" ASC

// SELECT "p".* FROM "product" "p" ORDER BY "p"."price" ASC
$select = $db->select('*')->from('product', 'p')->orderBy('p.price');

$stmt = $select->execute(); // or $select->query(), returns a PDOStatement or FALSE

// SELECT COUNT(*) FROM "product" GROUP BY "category_id"
$select = $db->select()->count()->from('product')->groupBy('category_id');
// SELECT MIN("price") FROM "product" GROUP BY "category_id"
$select = $db->select()->min('price')->from('product')->groupBy('category_id');
```

### Db::insert()

Create and optionally execute an insert command instance, which is a writer-command.

Writer commands (Insert, Update, Delete) method `execute()` is forwarded to the
writer-command method `exec()`.

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

Insert and execute shortcut call, when both arguments (`$table` and `$row`/`$rows`)
are provided:

```php
$result = $db->insert('product', [
    'name' => 'product-111',
    'price' => 111.11,
]); // returns TRUE or FALSE for single insert
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
]); // returns integer or FALSE

// equivalent to
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






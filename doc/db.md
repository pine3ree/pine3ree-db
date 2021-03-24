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

// same row using fecthOne with condition
$product = $db->fetchOne('product', ['id' => 42]);

$fiftyExpensiveProducts = $db->fetchAll('product', [
    ['price', '>', 1000.00],
], ['price' => 'ASC'], 50);

$tenMostExpensiveProducts = $db->fetchAll('product', null, ['price' => 'DESC'], 10);

$mostExpensiveProduct = $db->fetchOne('product', null, ['price' => 'DESC']);

``


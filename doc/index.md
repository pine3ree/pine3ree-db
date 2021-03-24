# p3-db

`P3\Db` is a small databae abstraction layer on top of `\PDO`.

It provides a simple sql builder and convenience methods for common CRUD opeations.

The package consists of a simple PDO connection wrapper that can use an existing connection
or instantiate a new lazy pdo connection on demand.

Basic database operations for retrieving, inserting, updating and deleting rows from/into
a given database table leverage a set of database Command classes, which in turn
compose the database connection itself and a corresponding sql abstraction statement object.

The sql-command building steps are forwarded to the corresponding sql abstraction layer object

# Installation

p3-db DBAL can be installed via composer

```
$ composer require pine3ree/p3-db
```

The package does not provide any stable version yet, so `"minimum-stability": "dev"`
setting is required in your `composer.json` file.

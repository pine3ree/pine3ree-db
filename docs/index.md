# p3-db

`P3\Db` is a small database abstraction layer on top of the `\PDO` library.

It provides a simple sql builder and convenience methods for common CRUD opeations.

The DBAL instance consists of a simple PDO connection wrapper that can either use
an existing connection or instantiate a new lazy pdo connection on demand.

Basic database operations for retrieving, inserting, updating and deleting rows from/into
a given database table leverage a set of database Command classes, which in turn
compose the database connection itself and a corresponding sql abstraction statement object.

The sql-command building operations are forwarded to the composed sql abstraction layer object,
while the sql statement preparation, parameter binding and command execution are
performed by the composed DBAL instance.

# Installation

p3-db DBAL requires php >= 7.1 and can be installed via composer

```bash
$ composer require pine3ree/p3-db
```

The package does not provide any stable version yet, so `"minimum-stability": "dev"`
setting is required in your `composer.json` file.

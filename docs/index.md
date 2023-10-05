# pine3ree-db

`pine3ree\Db` is a small database abstraction layer on top of the `\PDO` library.

It provides a simple sql builder and convenience methods for common CRUD opeations.

The DBAL instance consists of a simple PDO connection wrapper that can either use
an existing connection or instantiate a new lazy pdo connection on demand.

Basic database operations for retrieving, inserting, updating and deleting rows from/into
a given database table leverage a set of database Command classes, which in turn
compose the database connection itself and a corresponding sql abstraction statement object.

The sql-command building operations are forwarded to the composed sql abstraction layer object,
while the sql statement preparation, parameter binding and command execution are
performed by the composed DBAL instance.

## Installation

pine3ree-db DBAL requires php >= 7.1 and can be installed via composer

```bash
$ composer require pine3ree/pine3ree-db
```

The package does not provide any stable version yet, so `"minimum-stability": "dev"`
setting is required in your `composer.json` file.

## Features

The library's code is splitted into two main sections/namespaces:

- a `Sql` section in which sql generation of full statements or smaller fragments
  is abstracted

- a `Command` section which offers objects that actually send the sql statements
  to the database server by means ot the composed connection and retrieve the results of
  such operations such as row/record set for DQL statements and of number of
  affected rows for DML statements.
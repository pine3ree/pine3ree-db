<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use PDO;

/**
 * A SQL Driver provides methods for quoting identifier, aliases, values and
 * escaping strings. It may provide additional SQL-string building methods for
 * database backends that follows less-strictly the SQL standards.
 *
 * @property-read string $ql The left quote char, if any
 * @property-read string $qr The right quote char, if any
 * @property-read string $qv The value quote char, if any
 * @property-read string $name The driver short name
 */
interface DriverInterface
{
    /**
     * Get the driver name, usually matching the PDO driver name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Set the pdo connection, used in quoting values of type string
     *
     * @param PDO $pdo
     * @return void
     */
    public function setPDO(PDO $pdo): void;

    /**
     * Quote a string identifier representing a database table or a table column
     *
     * @param string $identifier The target identifier (column, table.column, t.column)
     * @return string
     */
    public function quoteIdentifier(string $identifier): string;

    /**
     * Quote a string to be used as a SQL alias
     *
     * @param string $alias The alias string to quote
     * @return string
     */
    public function quoteAlias(string $alias): string;

    /**
     * Quote a value, when needed, for usage in SQL expression
     *
     * Potentially dangerous: always prefer parameter binding
     *
     * @param mixed $value The target identifier (column or alias)
     */
    public function quoteValue($value): string;
}

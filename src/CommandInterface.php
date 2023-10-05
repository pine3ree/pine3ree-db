<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db;

use PDOStatement;
use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Statement as SqlStatement;

/**
 * A db-command sends a sql-statement to the composed database instance and
 * returns the result of its execution
 *
 * @property-read SqlStatement $sqlStatement
 */
interface CommandInterface
{
    /**
     * Execute the command, returning a PDOStatement or false for reader-commands,
     * int or false for writer-commands
     *
     * @return PDOStatement|int|false
     */
    public function execute();

    /**
     * Retrieve the composed SQL statement object
     *
     * @return SqlStatement
     */
    public function getSqlStatement(): SqlStatement;

    /**
     * Return the compiled SQL statement string that will be sent to the database
     *
     * @return string
     */
    public function getSQL(): string;

    /**
     * Return the parameter collector of the composed SQL statement available after
     * compiling the sql string
     *
     * @return Params|null
     */
    public function getParams(): ?Params;
}

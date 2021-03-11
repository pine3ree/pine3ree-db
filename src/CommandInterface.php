<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db;

use PDOStatement;
use P3\Db\Sql\Statement as SqlStatement;

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
     * Return the indexed params of the composed SQL statement available after
     * compiling the sql string
     *
     * @return array
     */
    public function getParams(): array;

    /**
     * Return the indexed params types of the composed SQL statement, available
     * after compiling the sql string
     *
     * @return array
     */
    public function getParamsTypes(): array;
}

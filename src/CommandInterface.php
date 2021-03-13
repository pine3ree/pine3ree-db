<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db;

use PDOStatement;
use P3\Db\Sql\Params;
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
     * Return the parameter collector of the composed SQL statement available after
     * compiling the sql string
     *
     * @return Params|null
     */
    public function getParams(): ?Params;

    /**
     * Return the indexed parameters of the composed SQL statement available after
     * compiling the sql string
     *
     * @return array
     */
    public function getParamsValues(): array;

    /**
     * Return the indexed parameters types of the composed SQL statement, available
     * after compiling the sql string
     *
     * @return array
     */
    public function getParamsTypes(): array;
}

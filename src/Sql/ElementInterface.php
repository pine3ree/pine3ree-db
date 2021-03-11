<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use P3\Db\Sql\DriverInterface;
use P3\Db\Exception\RuntimeException;

/**
 * Represents a generic SQL element
 *
 * A sql element provides the sql string it represents with the help of a sql driver
 *
 * Sql elements also support parameters for usage used in full sql-statements and
 * may belong to other elements
 */
interface ElementInterface
{
    /**
     * Build and return the parametrized SQL-string
     *
     * This method must call each inner element getSQL() method and then import
     * its parameters
     */
    public function getSQL(DriverInterface $driver = null): string;

    /**
     * Check if there are any parameters after compiling the sql string
     *
     * @return bool
     */
    public function hasParams(): bool;

    /**
     * Return the parameters values created for this element or imported from
     * inner elements after compiling the sql string, indexed by their sql markers
     *
     * This method returns an empty array if getSQL() has not been called after last
     * modification.
     *
     * @return array
     */
    public function getParams(): array;

    /**
     * Return the (generated/imported) parameter pdo-types (PDO::PARAM_*)
     *
     * @return array|int[]|array<string|int, int>
     */
    public function getParamsTypes(): array;

    /**
     * Check if element has a parent
     *
     * @return bool
     */
    public function hasParent(): bool;

    /**
     * Return the parent element, if any
     *
     * @return ElementInterface|null
     */
    public function getParent(): ?ElementInterface;

    /**
     * Set the parent element
     * Raises exception if parent is already set.
     *
     * @return void
     * @throws RuntimeException
     */
    public function setParent(ElementInterface $parent): void;
}

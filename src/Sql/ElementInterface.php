<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use P3\Db\Sql\Driver;
use P3\Db\Exception\RuntimeException;

/**
 * This abstract class represents a generic SQL element and is the ancestor
 * of all the other sql-related classes.
 */
interface ElementInterface
{
    /**
     * Build and return the parametrized SQL-string
     *
     * This method must call each inner element getSQL() method and then import
     * its parameters
     */
    public function getSQL(Driver $driver = null): string;

    /**
     * Check if there are any parameters after compiling the sql string
     *
     * @return bool
     */
    public function hasParams(): bool;

    /**
     * Return the parameters created for this element or imported from inner
     * elements or an empty array if getSQL() has not been called after last
     * modification.
     *
     * @return array
     */
    public function getParams(): array;

    /**
     * Return the (generated/imported) parameter pdo-types (PDO::PARAM_*)
     *
     * @param bool $returnPdoConstNames Return stringify version instead of actual int constants?
     *
     * @return array <int|string: int>
     */
    public function getParamsTypes(bool $returnPdoConstNames = false): array;

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

<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Predicate;

/**
 * This class represents a sql IN condition
 */
class In extends Predicate
{
    protected $identifier;
    protected $values;
    protected $has_null = false;
    protected $not = false;

    /**
     * @param string|Literal $identifier
     * @param array $values
     */
    public function __construct($identifier, array $values)
    {
        self::assertValidIdentifier($identifier);

        $this->identifier = $identifier;
        $this->values     = $values;
    }

    /**
     * {@inheritDoc}
     *
     * If one of the values is NULL then add an IS NULL clause
     *
     * @return string
     */
    public function getSQL(): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $identifier = $this->identifier instanceof Literal
            ? (string)$this->identifier
            : $this->quoteIdentifier($this->identifier);

        $operator = ($this->not ? "NOT " : "") . "IN";

        $values = [];
        $has_null = false;
        foreach ($this->values as $value) {
            if (null === $value) {
                $has_null = true;
                continue;
            }
            $values[] = $this->createNamedParam($value);
        }

        $ivl_sql = empty($values) ? "(NULL)" : "(" . implode(", ", $values) . ")";

        $null_sql = "";
        if ($has_null) {
            $null_sql = $this->not
                ? " AND {$identifier} IS NOT NULL"
                : " OR {$identifier} IS NULL";
        }

        return $this->sql = "{$identifier} {$operator} {$ivl_sql}{$null_sql}";
    }
}

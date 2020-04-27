<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Sql\Predicate;

/**
 * Class In
 */
class In extends Predicate
{
    protected $identifier;
    protected $values;
    protected $has_null = false;
    protected $not = false;

    public function __construct(string $identifier, array $values)
    {
        $this->identifier = $identifier;
        $this->values     = $values;
    }

    public function getSQL(): string
    {
        $identifier = $this->quoteIdentifier($this->identifier);
        $operator   = ($this->not ? "NOT " : "") . "IN";

        $values = [];
        $has_null = false;
        foreach ($this->values as $value) {
            if (null === $value) {
                $has_null = true;
            } else {
                $values[] = $this->createNamedParam($value);
            }
        }

        $ivl_sql = empty($values) ? "(NULL)" : "('" . implode("', '", $values) . "')";

        $null_sql = "";
        if ($has_null) {
            $null_sql = $this->not
                ? " AND {$identifier} IS NOT NULL"
                : " OR {$identifier} IS NULL";
        }

        return "{$identifier} {$operator} {$ivl_sql}{$null_sql}";
    }
}

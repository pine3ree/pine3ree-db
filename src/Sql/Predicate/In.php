<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Predicate;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Alias;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Identifier;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Predicate;
use P3\Db\Sql\Statement\Select;

use function get_class;
use function gettype;
use function implode;
use function is_array;
use function is_object;
use function sprintf;

/**
 * This class represents a sql IN condition
 */
class In extends Predicate
{
    /** @var string|Alias|Identifier|Literal */
    protected $identifier;

    /** @var array|Select */
    protected $value_list;

    /** @var bool */
    protected $has_null = false;

    /** @var bool */
    protected static $not = false;

    /**
     * @var int custom index counter
     */
    protected static $index = 0;

    /**
     * @param string|Alias|Identifier|Literal $identifier
     * @param array|Select $value_list
     */
    public function __construct($identifier, $value_list)
    {
        self::assertValidIdentifier($identifier);
        self::assertValidValueList($value_list);

        $this->identifier = $identifier;
        $this->value_list = $value_list;
    }

    protected static function assertValidValueList($value_list)
    {
        if (!is_array($value_list) && ! $value_list instanceof Select) {
            throw new InvalidArgumentException(sprintf(
                "A IN/NOT-IN predicate value list must be either an array of values"
                . " or a Select statement, '%s' provided!",
                is_object($value_list) ? get_class($value_list) : gettype($value_list)
            ));
        }

        if (is_array($value_list)) {
            foreach ($value_list as $value) {
                self::assertValidValue($value);
            }
        }
    }

    /**
     * {@inheritDoc}
     *
     * If one of the values is NULL then add an IS NULL clause
     *
     * @return string
     */
    public function getSQL(Driver $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        $this->resetParams();

        $driver = $driver ?? Driver::ansi();

        $identifier = self::quoteGenericIdentifier($this->identifier, $driver);

        $operator = static::$not ? Sql::NOT_IN : Sql::IN;

        if ($this->value_list instanceof Select) {
            $select_sql = $this->value_list->getSQL($driver);
            $this->importParams($this->value_list);

            return $this->sql = "{$identifier} {$operator} ({$select_sql})";
        }

        $values = [];
        $has_null = false;
        foreach ($this->value_list as $value) {
            if (null === $value) {
                $has_null = true;
                continue;
            }
            $values[] = $this->getValueSQL($value, null, 'in');
        }

        $ivl_sql = "(" . (empty($values) ? Sql::NULL : implode(", ", $values)) . ")";

        $null_sql = "";
        if ($has_null) {
            $null_sql = " " . (
                static::$not
                ? Sql::AND . " {$identifier} " . Sql::IS_NOT . " " . Sql::NULL
                : Sql::OR . " {$identifier} " . Sql::IS . " " . Sql::NULL
            );
        }

        $sql = "{$identifier} {$operator} {$ivl_sql}{$null_sql}";
        if ($has_null) {
            $sql = "({$sql})";
        }

        return $this->sql = $sql;
    }
}

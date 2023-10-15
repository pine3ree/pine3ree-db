<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql\Clause;

use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Clause;
use pine3ree\Db\Sql\Clause\ConditionalClauseAwareTrait;
use pine3ree\Db\Sql\DriverInterface;
use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Statement\Select;
use pine3ree\Db\Sql\TableAwareTrait;

/**
 * Base asbtract class that represents a SQL combine clause (UNION|INTERSECT|EXCEPT)
 *
 * @property-read string $name The combine sql-name (UNION|INTERSECT|EXCEPT)
 * @property-read bool $all Does this combine use the ALL quantifier?
 * @property-read string $select The combined Select instance
 */
abstract class Combine extends Clause
{
    use TableAwareTrait;
    use ConditionalClauseAwareTrait;

    protected static string $name = '[COMBINE]';

    /** @var Use the ALL quantifier instead of the implicit DISTINCT */
    private bool $all = false;

    private Select $select;

    /**
     *
     * @param Select $select
     * @param bool $all
     */
    public function __construct(Select $select, bool $all = false)
    {
        $this->all    = $all;
        $this->select = $select->parentIsNot($this) ? clone $select : $select;
        $this->select->setParent($this);
    }

    /**
     * @param string $combine The combination SQL name
     *
     * @throws InvalidArgumentException
     */
    protected static function assertValidCombine(string $combine): void
    {
        if (!Sql::isValidCombine($combine)) {
            throw new InvalidArgumentException(
                "Invalid or unsupported SQL combination type: '{$combine}' provided!"
            );
        }
    }

    public function hasParams(): bool
    {
        return $this->select->hasParams();
    }

    public function getParams(): ?Params
    {
        return $this->select->getParams();
    }

    public function getSQL(DriverInterface $driver = null, Params $params = null, bool $pretty = false): string
    {
        $combine = static::$name ?: $this->getName();

        if ($this->all) {
            $combine .= " " . Sql::ALL;
        }

        $select_sql = $this->select->getSQL($driver, $params, $pretty);

        $sep = $pretty ? "\n" : " ";

        return "{$combine}{$sep}{$select_sql}";
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        if ('name' === $name) {
            return static::$name ?? $this->getName();
        }
        if ('all' === $name) {
            return $this->all;
        }
        if ('select' === $name) {
            return $this->select;
        }

        return parent::__get($name);
    }

    public function __clone()
    {
        parent::__clone();
        $this->select = clone $this->select;
        $this->select->setParent($this);
    }
}

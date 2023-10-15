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
use pine3ree\Db\Sql\Clause\On;
use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\DriverInterface;
use pine3ree\Db\Sql\Identifier;
use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Predicate;
use pine3ree\Db\Sql\Predicate\Literal;
use pine3ree\Db\Sql\TableAwareTrait;

use function rtrim;
use function trim;

/**
 * Join represents a SQL-JOIN clause
 *
 * @property-read string $name The join sql-name ("join-type JOIN")
 * @property-read string $type The join type
 * @property-read string $table The joined-table
 * @property-read string|null $alias The joined-table alias, if any
 * @property-read On|Literal|Identifier|null $specification The ON-specification, if any
 * @property-read On|null $on The ON-clause (current or new instance) if the specification
 *      is not already set to a Literal
 */
class Join extends Clause
{
    use TableAwareTrait;
    use ConditionalClauseAwareTrait;

    protected static string $name = Sql::JOIN;

    private string $type;

    private ?string $alias = null;

    /** @var On|Literal|Identifier|null */
    private $specification;

    /**
     * @param string $type The join type
     * @param string $table The joined table name
     * @param string|null $alias The joined table alias, if any
     * @param On|Predicate|Predicate\Set|array|string|Literal|Identifier|null $specification
     *      The JOIN specification in form of a On conditional clause, an argument for
     *      builfing it, a Literal predicate used as it is or and Identifier translated into
     *      a USING clause.
     */
    public function __construct(string $type, string $table, string $alias = null, $specification = null)
    {
        self::assertValidJoin($type);

        $this->type = $type;
        $this->setTable($table);
        if (isset($alias) && '' !== ($alias = trim($alias))) {
            $this->alias = $alias;
        }

        if (!empty($specification)) {
            if ($specification instanceof Literal) {
                $this->specification = $specification;
            } elseif ($specification instanceof Identifier) {
                $this->specification = $specification;
            } else {
                $this->setConditionalClause('specification', On::class, $specification);
            }
        }
    }

    /**
     * @param string $join
     *
     * @throws InvalidArgumentException
     */
    protected static function assertValidJoin(string $join): void
    {
        if (!Sql::isValidJoin($join)) {
            throw new InvalidArgumentException(
                "Invalid or unsupported SQL JOIN type: '{$join}' provided!"
            );
        }
    }

    /**
     * Return the JOIN type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Return the JOIN table
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Return the JOIN table alias
     */
    public function getAlias(): ?string
    {
        return $this->alias;
    }

    /**
     * Return the JOIN specification: either a literal-predicate, a sql-identifier
     * object for the USING clause or an On clause instance
     *
     * @return On|Literal|Identifier
     */
    public function getSpecification()
    {
        return $this->specification;
    }

    public function getSQL(DriverInterface $driver = null, Params $params = null): string
    {
        if ($this->hasValidSqlCache($driver, $params)) {
            return $this->sql;
        }

        $this->driver = $driver; // Set last used driver argument
        $this->params = null; // Reset previously collected params, if any

        $driver = $driver ?? Driver::ansi();
        $params = $params ?? ($this->params = new Params());

        $table = $driver->quoteIdentifier($this->table);
        if (!empty($this->alias)) {
            $table .= " " .  $driver->quoteAlias($this->alias);
        }

        $join = Sql::JOIN;
        if (!empty($this->type)) {
            $join = "{$this->type} {$join}";
        }

        if (empty($this->specification)) {
            return $this->sql = "{$join} {$table}";
        }

        $specification_sql = '';
        if ($this->specification instanceof Literal) {
            $specification_sql = $this->specification->getSQL();
        } elseif ($this->specification instanceof Identifier) {
            $specification_sql = "USING(" . $this->specification->getSQL($driver) . ")";
        } elseif ($this->specification instanceof On) {
            $specification_sql = $this->getConditionalClauseSQL('specification', $driver, $params);
        }

        return $this->sql = rtrim("{$join} {$table} {$specification_sql}");
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        if ('name' === $name) {
            return Sql::JOIN;
        }
        if ('type' === $name) {
            return $this->type;
        }
        if ('table' === $name) {
            return $this->table;
        }
        if ('alias' === $name) {
            return $this->alias;
        }
        if ('specification' === $name) {
            return $this->specification;
        }
        if ('on' === $name) {
            if ($this->specification === null) {
                $this->specification = new On();
                $this->specification->setParent($this);
                return $this->specification;
            }
            if ($this->specification instanceof On) {
                return $this->specification;
            }
            return null;
        }

        return parent::__get($name);
    }

    public function __clone()
    {
        parent::__clone();
        if ($this->specification instanceof On) {
            $this->specification = clone $this->specification;
            $this->specification->setParent($this);
        }
    }
}

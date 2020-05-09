<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use P3\Db\Sql;
use P3\Db\Sql\Driver\Ansi;
use P3\Db\Sql\Literal;
use P3\Db\Sql\PredicateSet;
use P3\Db\Sql\Statement\Select;
use PDO;
use Throwable;

use function addcslashes;
use function count;
use function implode;
use function is_bool;
use function is_int;
use function is_numeric;
use function is_string;
use function ltrim;
use function rtrim;
use function str_replace;
use function strpos;
use function substr;
use function trim;

/**
 * The default ANSI SQL Driver
 */
abstract class Driver
{
    /**
     * @var PDO|null
     */
    protected $pdo;

    /**
     * @var string The left quote char for identifiers/aliases, default is ANSI '"'
     */
    protected $ql;

    /**
     * @var string The right quote char for identifiers/aliases, default is ANSI '"'
     */
    protected $qr;

    protected $qlr;

    /**
     * @var string The quote char for values, default is single-quote char "'"
     */
    protected $qv;

    /**
     * The basic singleton ansi driver
     * @var self
     */
    private static $ansi;

    /**
     * @param PDO $pdo the database connection, if any
     * @param string $ql left-quote char
     * @param string $qr right-quote char
     * @param string $qv quote char for values
     */
    public function __construct(PDO $pdo = null, string $ql = '"', string $qr = '"', string $qv = "'")
    {
        $this->pdo = $pdo;

        $this->ql = $ql;
        $this->qr = $qr;
        $this->qv = $qv;

        $this->qlr = "{$ql}{$qr}";
    }

    /**
     * Quote a yet unquoted identifier that represents a table column
     *
     * @param string $identifier The target identifier (column, table.column, t.column)
     * @return string
     */
    public function quoteIdentifier(string $identifier): string
    {
        if ($identifier === '*' || empty($this->qlr) || $this->isQuoted($identifier)) {
            return $identifier;
        }

        $ql = $this->ql;
        $qr = $this->qr;

        if (false === strpos($identifier, '.')) {
            return "{$ql}{$identifier}{$qr}";
        }

        $quoted = $ql . str_replace('.', "{$qr}.{$ql}", $identifier) . $qr;
        $quoted = str_replace("{$ql}*{$qr}", '*', $quoted); // unquote the sql asterisk

        return $quoted;
    }

    protected function isQuoted(string $identifier): bool
    {
        return (
               $this->ql === substr($identifier, 0, 1)
            && $this->qr === substr($identifier, -1)
        );
    }

    /**
     * Quote an alias
     *
     * @param string $alias The alias string to quote
     * @return string
     */
    public function quoteAlias(string $alias): string
    {
        if (empty($this->qlr)) {
            return $alias;
        }

        $ql = $this->ql;
        $qr = $this->qr;

        return $ql. ltrim(rtrim($alias, $qr), $ql) . $qr;
    }

    /**
     * Quote a value, when appliable, for SQL expression
     *
     * Potentially dangerous: always prefer parameter binding
     *
     * @param mixed $value The target identifier (column or alias)
     */
    public function quoteValue($value): string
    {
        if (null === $value) {
            return 'NULL';
        }

        if (isset($this->pdo)) {
            try {
                if (is_int($value)) {
                    $parameter_type = PDO::PARAM_INT;
                } elseif (is_bool($value)) {
                    $parameter_type = PDO::PARAM_INT;
                    $value = (int)$value;
                } else {
                    $parameter_type = PDO::PARAM_STR;
                    if (!is_string($value)) {
                        $value = (string)$value;
                    }
                }
                $quoted = $this->pdo->quote($value, $parameter_type);
                if ($quoted !== false) {
                    return $quoted;
                }
            } catch (Throwable $ex) {
                // do nothing
            }
        }

        if (is_int($value)) {
            return (string)$value;
        }
        if (is_bool($value)) {
            return (string)(int)$value;
        }

        if (!is_string($value)) {
            $value = (string)$value;
        }

        return "{$this->qv}{$this->escape($value)}{$this->qv}";
    }

    /**
     * Escape a string for SQL expression
     *
     * Potentially dangerous: always prefer parameter binding
     *
     * @param string $value
     */
    public function escape(string $value): string
    {
        return addcslashes($value, "\x00\n\r\\'\"\x1a");
    }

    public function setPDO(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Return the basic ANSI driver
     *
     * @return self
     */
    public static function ansi(): self
    {
        return self::$ansi ?? self::$ansi = new Ansi();
    }

    public function getSelectColumnsSQL(Select $select): string
    {
        $alias = $select->alias;
        $columns = $select->columns;

        if (empty($columns)) {
            return $alias ? $this->quoteAlias($alias) . ".*" : "*";
        }

        $sqls = [];
        foreach ($columns as $key => $column) {
            if ($column === Sql::ASTERISK) {
                $column_sql = $alias ? $this->quoteAlias($alias) . ".*" : "*";
            } else {
                if ($column instanceof Literal) {
                    $column_sql = $column->getSQL();
                } elseif ($column instanceof Select) {
                    $column_sql = $column->getSQL($this);
                    $select->importParams($column);
                } else {
                    $column_sql = $this->quoteIdentifier(
                        $select->normalizeColumn($column)
                    );
                }
                // add alias?
                if (!is_numeric($key) && $key !== '' && $key !== $column) {
                    $column_sql .= " AS " . $this->quoteAlias($key);
                }
            }
            $sqls[] = $column_sql;
        }

        return trim(implode(", ", $sqls));
    }

    public function getSelectFromSQL(Select $select): string
    {
        $from  = $select->from;
        $table = $select->table;
        $alias = $select->alias;

        if ($from instanceof Select) {
            $from = "(" . $from->getSQL($this) . ")";
            $select->importParams($from);
        } else {
            $from = $this->quoteIdentifier($table);
        }

        if (!empty($alias)) {
            $from = trim("{$from} " . $this->quoteAlias($alias));
        }

        return "FROM {$from}";
    }

    public function getSelectJoinSQL(Select $select): string
    {
        $sqls = [];
        foreach ($select->joins as $join) {
            $type  = $join['type'];
            $table = $this->quoteIdentifier($join['table']);
            $alias = $this->quoteAlias($join['alias']);
            $cond  = $join['cond'];

            $cond_sql = $cond->getSQL($this);
            if ($cond->hasParams()) {
                $select->importParams($cond);
            }

            $sqls[] = trim("{$type} JOIN {$table} {$alias} {$cond_sql}");
        }

        return trim(implode(" ", $sqls));
    }

    public function getPredicateSetSQL(PredicateSet $predicateSet)
    {
        $predicates = $predicateSet->getPredicates();
        if (empty($predicates)) {
            return '';
        }

        $sqls = [];
        foreach ($predicates as $predicate) {
            $sql = $predicate->getSQL($this);
            if (Sql::isEmptySQL($sql)) {
                continue;
            }
            $sqls[] = $sql;
            $predicateSet->importParams($predicate);
        }

        if (empty($sqls)) {
            return '';
        }

        if (1 === count($sqls)) {
            return $sqls[0];
        }

        $AND_OR = $predicateSet->getCombinedBy();

        return "(" . trim(implode(" {$AND_OR} ", $sqls)) . ")";
    }
}

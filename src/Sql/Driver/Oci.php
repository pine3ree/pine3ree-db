<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Driver;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Driver\Feature\LimitSqlProvider;
use P3\Db\Sql\Driver\Feature\SelectColumnsSqlProvider;
use P3\Db\Sql\Driver\Feature\SelectSqlDecorator;
use P3\Db\Sql\Expression;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Statement\Select;
use PDO;

use function end;
use function explode;
use function implode;
use function get_class;
use function gettype;
use function is_object;
use function sprintf;
use function strpos;
use function substr;

use const PHP_INT_MAX;

/**
 * Oci sql-driver
 */
class Oci extends Driver implements
    LimitSqlProvider,
    SelectColumnsSqlProvider,
    SelectSqlDecorator
{
    /**
     * @const string Quoted table alias for LIMIT+OFFSET statements
     */
    private const TB = '"__oci_tb"';

    /**
     * @const string Quoted ROWNUM alias for LIMIT+OFFSET statements
     */
    private const RN = '"__oci_rn"';

    public function __construct(PDO $pdo = null)
    {
        parent::__construct($pdo, '"', '"', "'");
    }

    /**
     * {@inheritDoc}
     *
     * Quoting Oracle identifiers may introduce errors as Oracle creates uppercase
     * table and column names if not quoted themself on creation.
     */
    public function quoteIdentifier(string $identifier): string
    {
        if ($identifier === '*' || $this->isQuoted($identifier)) {
            return $identifier;
        }

        // table and column names that have characters other than uppercase letters
        // or numbers mus be quoted
        if (false === strpos($identifier, '.')) {
            if (preg_match('/(:?^_|[^A-Z0-9])/', $identifier)) {
                return parent::quoteIdentifier($identifier);
            }
            return $identifier;
        }

        $segments = explode('.', $identifier);
        foreach ($segments as $i => $segment) {
            $segments[$i] = $this->quoteIdentifier($segment);
        }

        return implode('.', $segments);
    }

    public function decorateSelectSQL(Select $select): ?string
    {
        $limit  = $select->limit;
        $offset = $select->offset;

        if (isset($limit) && (!isset($offset) || $offset === 0)) {
            $select_sql = $this->generateSelectSQL($select);
            $limit = $this->createParam($select, $limit + $offset, PDO::PARAM_INT, 'limit');

            return "SELECT * FROM ({$select_sql}) WHERE ROWNUM <= {$limit}";
        }

        if (isset($offset) && $offset > 0) {
            $tb = self::TB;
            $rn = self::RN;

            $select_sql = $this->generateSelectSQL($select);

            $limit = isset($limit)
                ? $this->createParam($select, $limit + $offset, PDO::PARAM_INT, 'limit')
                : PHP_INT_MAX;

            $limit_sql = "SELECT {$tb}.*, ROWNUM AS {$rn}"
                . " FROM ({$select_sql}) {$tb}"
                . " WHERE ROWNUM <= {$limit}";

            $offset = $this->createParam($select, $offset, PDO::PARAM_INT, 'offset');

            return "SELECT * FROM ({$limit_sql}) WHERE {$rn} > {$offset}";
        }

        return null;
    }

    public function getSelectColumnsSQL(Select $select, bool &$cache = true): string
    {
        $table   = $select->table;
        $alias   = $select->alias;
        $columns = $select->columns;
        $joins   = $select->joins;

        $add_tb_prefix = !empty($table) && !empty($joins);

        if (empty($columns)) {
            $columns = ['*' => '*'];
        }

        $sqls = [];
        foreach ($columns as $key => $column) {
            if ($column === Sql::ASTERISK) {
                $prefix = !empty($alias) ? $this->quoteAlias($alias) : null;
                if (empty($prefix) && $add_tb_prefix) {
                    $prefix = $this->quoteIdentifier($table);
                }
                $sqls[] = !empty($prefix) ? "{$prefix}.*" : "*";
                continue; // no-alias
            }

            if (is_string($column)) {
                if (false === strpos($column, '.')) {
                    $prefix = !empty($alias) ? $this->quoteAlias($alias) : null;
                    if (empty($prefix) && $add_tb_prefix) {
                        $prefix = $this->quoteIdentifier($table);
                    }
                    $column_sql = !empty($prefix) ? "{$prefix}.{$column}" : $column;
                } else {
                    $column_sql = $column;
                }
            } elseif ($column instanceof Literal) {
                $column_sql = $column->getSQL();
            } elseif ($column instanceof Expression || $column instanceof Select) {
                $column_sql = $column->getSQL($this);
                $this->importParams($select, $column);
                $cache = $cache && $column instanceof Expression && !$column->hasParams();
            } else {
                // @codeCoverageIgnoreStart
                // should be unreacheable due to table-column validity assertion
                throw new InvalidArgumentException(sprintf(
                    "Invalid db-table column type! Allowed types are: string, Literal,"
                    . " Expression, Select, `%s` provided!",
                    is_object($column) ? get_class($column) : gettype($column)
                ));
                // @codeCoverageIgnoreEnd
            }

            // add alias?
            if (!is_numeric($key) && $key !== '' && $key !== $column) {
                $column_sql .= " AS " . $this->quoteAlias($key);
            } elseif (is_string($column)) {
                $parts = explode('.', $column);
                $column = end($parts);
                $column_sql .= " AS " . $this->quoteAlias($column);
            }

            $sqls[] = $column_sql;
        }

        return trim(implode(", ", $sqls));
    }

    public function getLimitSQL(Select $select): string
    {
        return '';
    }
}

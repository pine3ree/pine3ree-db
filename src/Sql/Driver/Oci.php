<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Driver;

use P3\Db\Sql;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Expression;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Statement;
use P3\Db\Sql\Statement\Select;
use PDO;

use function explode;
use function implode;
use function strpos;
use function substr;

use const PHP_INT_MAX;

/**
 * Oci sql-driver
 */
class Oci extends Driver
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
        if ($identifier === '*' || empty($this->qlr) || $this->isQuoted($identifier)) {
            return $identifier;
        }

        // table and column names starting with the underscore char must be quoted
        if (false === strpos($identifier, '.')) {
            if ('_' === substr($identifier, 0, 1)) {
                return parent::quoteIdentifier($identifier);
            }
            return $identifier;
        }

        $segments = explode('.', $identifier);
        foreach ($segments as $i => $segment) {
            if ('_' === substr($segment, 0, 1)) {
                $segments[$i] = parent::quoteIdentifier($segment);
            }
        }

        return implode('.', $segments);
    }

    public function decorateSelectSQL(Select $select, string $sql): string
    {
        $limit  = $select->limit;
        $offset = $select->offset;

        if (isset($limit) && !isset($offset)) {
            $limit = $select->createNamedParam($limit + $offset, PDO::PARAM_INT);
            return "SELECT * FROM ({$sql}) WHERE ROWNUM <= {$limit}";
        }

        if (isset($offset) && $offset > 0) {
            $tb = self::TB;
            $rn = self::RN;
            $limit = isset($limit)
                ? $select->createNamedParam($limit + $offset, PDO::PARAM_INT)
                : PHP_INT_MAX
            ;
            $offset = $select->createNamedParam($offset, PDO::PARAM_INT);
            $limit_sql = "SELECT {$tb}.*, ROWNUM AS {$rn} FROM ({$sql}) {$tb} WHERE ROWNUM <= {$limit}";
            return "SELECT * FROM ({$limit_sql}) WHERE {$rn} > {$offset}";
        }

        return $sql;
    }

    public function getColumnsSQL(Select $select): string
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
                $prefix = $alias ? $this->quoteAlias($alias) : null;
                if (empty($prefix) && $add_tb_prefix && !empty($table)) {
                    $prefix = $this->quoteIdentifier($table);
                }
                $sqls[] = $prefix ? "{$prefix}.*" : "*";
                continue; // no-alias
            }

            if (is_string($column)) {
                $column_sql = $this->quoteIdentifier(
                    $select->normalizeColumn($column, $add_tb_prefix)
                );
            } elseif ($column instanceof Literal) {
                $column_sql = $column->getSQL();
            } elseif ($column instanceof Expression || $column instanceof Select) {
                $column_sql = $column->getSQL($this);
                $select->importParams($column);
            } else {
                continue; // skip or throw?
            }

            // add alias?
            if (!is_numeric($key) && $key !== '' && $key !== $column) {
                $column_sql .= " AS " . $this->quoteAlias($key);
            } elseif (is_string($column)) {
                $column = end(explode('.', $column));
                $column_sql .= " AS " . $this->quoteAlias($column);
            }

            $sqls[] = $column_sql;
        }

        return trim(implode(", ", $sqls));
    }

    public function getLimitSQL(Statement $statement): string
    {
        return '';
    }
}

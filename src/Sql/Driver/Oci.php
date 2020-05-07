<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Driver;

use PDO;
use P3\Db\Sql;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Statement\Select;
use P3\Db\Sql\Statement\Insert;

use function strtoupper;

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
            if ('_' === substr($segment, 0, 1)) {
                $segments[$i] = parent::quoteIdentifier($segment);
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
        // quote any unquoted table alias prefix
        $tb_aliases = [];
        if ($tb_alias = $select->alias) {
            $tb_aliases[] = $select->alias;
        }
        foreach ($select->joins as $join) {
            if ($tb_alias = $join['alias']) {
                $tb_aliases[] = $tb_alias;
            }
        }
        foreach ($tb_aliases as $tb_alias) {
            $sql = str_replace(" {$tb_alias}.", " {$this->quoteAlias($tb_alias)}.", $sql);
        }

        $limit  = $select->limit;
        $offset = $select->offset;

        if (isset($limit) && !isset($offset)) {
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
            return "SELECT * FROM ($limit_sql) WHERE {$rn} > {$offset}";
        }

        return $sql;
    }

    public function getColumnsSQL(Select $select): string
    {
        $sqls = [];
        $tb_alias = $select->alias;
        foreach ($select->columns as $key => $column) {
            if ($column === Sql::ASTERISK) {
                $column_sql = $tb_alias ? $this->quoteAlias($tb_alias) . ".*" : "*";
            } else {
                if ($column instanceof Literal) {
                    $column_sql = $column->getSQL();
                } else {
                    $column_sql = $this->quoteIdentifier(
                        $select->normalizeColumn($column)
                    );
                }
                // add alias
                if (!is_numeric($key) && $key !== '') {
                    $column_sql .= " AS " . $this->quoteAlias($key);
                } elseif (! $column instanceof Literal) {
                    $column = end(explode('.', $column));
                    $column_sql .= " AS " . $this->quoteAlias($column);
                }
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

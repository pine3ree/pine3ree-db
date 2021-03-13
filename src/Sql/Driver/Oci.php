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
use P3\Db\Sql\Driver\Feature\SelectDecorator;
use P3\Db\Sql\Driver\Feature\SelectSqlDecorator;
use P3\Db\Sql\Expression;
use P3\Db\Sql\Literal;
use P3\Db\Sql\Params;
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
use function strtoupper;

/**
 * Oci sql-driver
 */
class Oci extends Driver implements
    LimitSqlProvider,
    SelectColumnsSqlProvider,
    SelectDecorator,
    SelectSqlDecorator
{
    /**
     * @const string Quoted table alias for LIMIT+OFFSET statements
     */
    private const TB = '__oci_tb';

    /**
     * @const string Quoted ROWNUM alias for LIMIT+OFFSET statements
     */
    private const RN = '__oci_rn';

    private const RESERVED_WORDS = [
        'ACCESS' => true,
        'ADD' => true,
        'ALL' => true,
        'ALTER' => true,
        'AND' => true,
        'ANY' => true,
        'ARRAYLEN' => true,
        'AS' => true,
        'ASC' => true,
        'AUDIT' => true,
        'BETWEEN' => true,
        'BY' => true,
        'CHAR' => true,
        'CHECK' => true,
        'CLUSTER' => true,
        'COLUMN' => true,
        'COMMENT' => true,
        'COMPRESS' => true,
        'CONNECT' => true,
        'CREATE' => true,
        'CURRENT' => true,
        'DATE' => true,
        'DECIMAL' => true,
        'DEFAULT' => true,
        'DELETE' => true,
        'DESC' => true,
        'DISTINCT' => true,
        'DROP' => true,
        'ELSE' => true,
        'EXCLUSIVE' => true,
        'EXISTS' => true,
        'FILE' => true,
        'FLOAT' => true,
        'FOR' => true,
        'FROM' => true,
        'GRANT' => true,
        'GROUP' => true,
        'HAVING' => true,
        'IDENTIFIED' => true,
        'IMMEDIATE' => true,
        'IN' => true,
        'INCREMENT' => true,
        'INDEX' => true,
        'INITIAL' => true,
        'INSERT' => true,
        'INTEGER' => true,
        'INTERSECT' => true,
        'INTO' => true,
        'IS' => true,
        'LEVEL' => true,
        'LIKE' => true,
        'LOCK' => true,
        'LONG' => true,
        'MAXEXTENTS' => true,
        'MINUS' => true,
        'MODE' => true,
        'MODIFY' => true,
        'NOAUDIT' => true,
        'NOCOMPRESS' => true,
        'NOT' => true,
        'NOTFOUND' => true,
        'NOWAIT' => true,
        'NULL' => true,
        'NUMBER' => true,
        'OF' => true,
        'OFFLINE' => true,
        'ON' => true,
        'ONLINE' => true,
        'OPTION' => true,
        'OR' => true,
        'ORDER' => true,
        'PCTFREE' => true,
        'PRIOR' => true,
        'PRIVILEGES' => true,
        'PUBLIC' => true,
        'RAW' => true,
        'RENAME' => true,
        'RESOURCE' => true,
        'REVOKE' => true,
        'ROW' => true,
        'ROWID' => true,
        'ROWLABEL' => true,
        'ROWNUM' => true,
        'ROWS' => true,
        'SELECT' => true,
        'SESSION' => true,
        'SET' => true,
        'SHARE' => true,
        'SIZE' => true,
        'SMALLINT' => true,
        'SQLBUF' => true,
        'START' => true,
        'SUCCESSFUL' => true,
        'SYNONYM' => true,
        'SYSDATE' => true,
        'TABLE' => true,
        'THEN' => true,
        'TO' => true,
        'TRIGGER' => true,
        'UID' => true,
        'UNION' => true,
        'UNIQUE' => true,
        'UPDATE' => true,
        'USER' => true,
        'VALIDATE' => true,
        'VALUES' => true,
        'VARCHAR' => true,
        'VARCHAR2' => true,
        'VIEW' => true,
        'WHENEVER' => true,
        'WHERE' => true,
        'WITH' => true,
    ];

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
            if ('_' === $identifier[0]
                || isset(self::RESERVED_WORDS[strtoupper($identifier)])
            ) {
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

    public function decorateSelectSQL(Select $select, Params $params): string
    {
        $limit  = $select->limit;
        $offset = $select->offset;

        if (isset($limit) && (!isset($offset) || $offset === 0)) {
            $select_sql = $this->generateSelectSQL($select, $params);
            $limit = $params->createParam($limit, PDO::PARAM_INT, 'limit');

            return "SELECT * FROM ({$select_sql}) WHERE ROWNUM <= {$limit}";
        }

        if (isset($offset) && $offset > 0) {
            $qtb = $this->quoteAlias(self::TB);
            $qrn = $this->quoteAlias(self::RN);

            $select_sql = $this->generateSelectSQL($select, $params);
            $select_sql = "SELECT {$qtb}.*, ROWNUM AS {$qrn}"
                . " FROM ({$select_sql}) {$qtb}";

            if (isset($limit)) {
                $limit = $params->createParam($limit + $offset, PDO::PARAM_INT, 'limit');
                $select_sql .= " WHERE ROWNUM <= {$limit}";
            }

            $offset = $params->createParam($offset, PDO::PARAM_INT, 'offset');

            return "SELECT * FROM ({$select_sql}) WHERE {$qrn} > {$offset}";
        }

        return $this->generateSelectSQL($select, $params);
    }

    public function decorateSelect(Select $select, Params $params = null): Select
    {
        $limit  = $select->limit;
        $offset = $select->offset;

        if (isset($limit) && (!isset($offset) || $offset === 0)) {
            $from = clone $select;
            $from->limit(null);

            $wrapper = new Select('*', $from, self::TB);
            $wrapper->where->lte(new Literal("ROWNUM"), $limit);

            return $wrapper;
        }

        if (isset($offset) && $offset > 0) {
            $tb0 = self::TB . '0';
            $tb1 = self::TB . '1';

            $from = clone $select;
            $from->offset(null)->limit(null);

            // create a select to gather ROWNUM values
            $inner = new Select('*', $from, $tb0);
            $inner->column(new Literal("ROWNUM"), self::RN);

            if (isset($limit)) {
                $inner->where->lte(new Literal("ROWNUM"), $offset + $limit);
            }

            $outer = new Select('*', $inner, $tb1);
            $outer->where->gt(new Sql\Alias(self::RN), $offset);

            return $outer;
        }

        return $select;
    }

    public function getSelectColumnsSQL(Select $select, Params $params, bool &$cache = true): string
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
                    $q_column = $this->quoteIdentifier($column);
                    $column_sql = !empty($prefix) ? "{$prefix}.{$q_column}" : $q_column;
                } else {
                    $column_sql = $this->quoteIdentifier($column);
                }
            } elseif ($column instanceof Literal) {
                $column_sql = $column->getSQL();
            } elseif ($column instanceof Expression || $column instanceof Select) {
                $column_sql = $column->getSQL($this, $params);
                $cache = false;
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
                if ($column !== "*") {
                    $column_sql .= " AS " . $this->quoteAlias($column);
                }
            }

            $sqls[] = $column_sql;
        }

        return trim(implode(", ", $sqls));
    }

    public function getLimitSQL(Select $select, Params $params): string
    {
        return '';
    }
}

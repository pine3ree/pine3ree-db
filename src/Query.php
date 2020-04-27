<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db;

use PDO;
use RuntimeException;
use P3\Db\Sql;

/**
 * Class Query
 */
abstract class Query
{
    protected $table;
    protected $alias;
    protected $pk = 'id';

    protected $params = [];
    protected $params_types = [];

    /**
     * @var string|null
     */
    protected $sql;

    /**
     * @var string[]
     */
    protected $sqls = [];

    public const TYPE_SELECT = 'SELECT';
    public const TYPE_INSERT = 'INSERT';
    public const TYPE_UPDATE = 'UPDATE';
    public const TYPE_DELETE = 'DELETE';

    public const CLAUSE_WHERE  = 'WHERE';
    public const CLAUSE_JOIN   = 'JOIN';
    public const CLAUSE_HAVING = 'HAVING';
    public const CLAUSE_ON     = 'ON';

    const JOIN_AUTO          = '';
    const JOIN_INNER         = 'INNER';
    const JOIN_CROSS         = 'CROSS';
    const JOIN_LEFT          = 'LEFT';
    const JOIN_RIGHT         = 'RIGHT';
    const JOIN_STRAIGHT      = 'STRAIGHT_JOIN';
    const JOIN_NATURAL       = 'NATURAL';
    const JOIN_NATURAL_LEFT  = 'NATURAL LEFT';
    const JOIN_NATURAL_RIGHT = 'NATURAL RIGHT';

    public const SORT_ASC  = 'ASC';
    public const SORT_DESC = 'DESC';

    abstract public function getSQL(): string;

    public function getParams(): array
    {
        return $this->params;
    }

    public function getParamsTypes(): array
    {
        return $this->params_types;
    }

    /**
     * Validate and set the query table/alias
     *
     * @param array|string $table
     * @param string|null $alias
     * @return $this
     */
    protected function setTable($table, string $alias = null): self
    {
        if (isset($this->table)) {
            throw new RuntimeException(
                "Cannot change db-table name for this query, table already set to `{$this->table}`!"
            );
        }

        if (is_array($table)) {
            $key = key($table);
            $table = current($table);
            if (!is_numeric($key) && $key !== '') {
                $this->alias = $key;
            }
        }

        $this->table = $table;
        if (!empty($alias)) {
            $this->alias = $alias;
        }

        return $this;
    }

    protected function isEmptyStatement($sql): bool
    {
        return !is_string($sql) || '' !== $sql;
    }

    protected function isNotEmptyStatement($sql): bool
    {
        return is_string($sql) && '' !== $sql;
    }

    protected function normalizeColumn(string $column): string
    {
        $identifier = trim($column, '.`');
        if (false === strpos($identifier, '.')) {
            $identifier = $this->alias ? "{$this->alias}.{$identifier}" : $identifier;
        }

        return $this->quoteIdentifier($identifier);
    }

    protected function quoteIdentifier(string $identifier, string $q = '`'): string
    {
        if ($q) {
            if ($q === substr($identifier, 0, 1) && substr($identifier, -1) === $q) {
                return $identifier;
            }

            $identifier = trim($identifier, ".{$q}");
            if (false === strpos($identifier, '.')) {
                return "{$q}{$identifier}{$q}";
            }

            return $q . str_replace(".", "{$q}.{$q}", $identifier) . $q;
        }

        return $identifier;
    }

    protected function quoteAlias(string $alias, string $q = '`'): string
    {
        return $q . trim($alias, $q) . $q;
    }

    protected function createNamedParam($value, int $param_type = null): string
    {
        return $this->createPositionalParam($value, $param_type);
        static $i = 1;

        $marker = ":_v{$i}";

        $this->setParam($marker, $value, $param_type);

        $i = $i < 999999 ? ($i + 1) : 1;

        return $marker;
    }

    protected function createPositionalParam($value, int $param_type = null): string
    {
        static $index = 1;

        $this->setParam($index, $value, $param_type);

        $index = $index < 999999 ? ($index + 1) : 1;

        return '?';
    }

    private function setParam($key, $value, int $param_type = null)
    {
        $this->params[$key] = $value;

        if (!isset($param_type)) {
            if (is_null($value)) {
                $param_type = PDO::PARAM_NULL;
            } elseif (is_int($value)) {
                $param_type = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $param_type = PDO::PARAM_BOOL;
            } else {
                $param_type = PDO::PARAM_STR;
            }
        }

        $this->params_types[$key] = $param_type;
    }

    public function escape($value): string
    {
		return str_replace(
            ["\\",   "\0",  "\n",  "\r",  "\x1a", "'",  '"'],
            ["\\\\", "\\0", "\\n", "\\r", "\Z",   "\'", '\"'],
            $value
        );
    }
}

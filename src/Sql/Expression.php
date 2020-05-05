<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use JsonSerializable;
use PDO;
use P3\Db\Sql\ExpressionInterface;

use function addcslashes;
use function is_bool;
use function is_int;
use function is_null;
use function is_string;
use function str_replace;
use function strpos;
use function trim;

/**
 * This abstract class represents a generic SQL Expression and is the ancestor
 * of all the other sql-related classes.
 */
class Expression implements ExpressionInterface
{
    /**
     * @var string The rendered SQL statement string with optional parameter markers
     */
    protected $sql;

    /**
     * @var string The quote char for identifiers
     */
    protected $qi = '`';

    /**
     * @var string The quote char for values
     */
    protected $qv = "'";

    /**
     * @var array<int|string: mixed> A collection of indexed substitution parameters
     */
    protected $params = [];

    /**
     * A collection of indexed types for substitution parameters
     * Types are expressed using PDO::PARAM_* constants
     *
     * @var array<int|string: int>
     */
    protected $params_types = [];

    /**
     * The parameter index counter
     *
     * @var int
     */
    protected static $index = 1;

    /**
     * @const int The maximun numeric index after which the param counter reset to 1
     */
    protected const MAX_INDEX = 999999;


    public function __construct(string $expression, array $params = [])
    {
        $this->sql = trim($expression);
        foreach ($params as $key => $value) {
            $this->setParam($key, $value);
        }
    }

    public function hasParams(): bool
    {
        return !empty($this->params);
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getParamsTypes(): array
    {
        return $this->params_types;
    }

    /**
     * Import parameters and types from inner expression
     *
     * @param self $expr
     */
    protected function importParams(self $expr): void
    {
        if (empty($expr->params)) {
            return;
        }

        foreach ($expr->params as $key => $value) {
            $this->params[$key] = $value;
            $this->params_types[$key] = $expr->params_types[$key] ?? PDO::PARAM_STR;
        }
    }

    public function getSQL(): string
    {
        return $this->sql ?? '';
    }

    public function __toString(): string
    {
        return $this->getSQL();
    }

    protected function isEmptySQL($sql): bool
    {
        return !is_string($sql) || '' === trim($sql);
    }

    /**
     * Quote a yet unquoted identifier that represents a table column
     *
     * @param string $identifier The target identifier (column, table.column, t.column)
     * @return string
     */
    protected function quoteIdentifier(string $identifier): string
    {
        $q = $this->qi;

        if ($identifier === '*' || $q === '') {
            return $identifier;
        }

        if ($this->isQuoted($identifier)) {
            return $identifier;
        }

        $identifier = trim($identifier, $q);

        if (false === strpos($identifier, '.')) {
            return "{$q}{$identifier}{$q}";
        }

        $quoted = $q . str_replace('.', "{$q}.{$q}", $identifier) . $q;
        $quoted = str_replace("{$q}*{$q}", '*', $quoted); // unquote the sql asterisk

        return $quoted;
    }

    protected function isQuoted(string $identifier)
    {
        $q = $this->qi;

        return ($q !== ''
            && $q === substr($identifier, 0, 1)
            && $q === substr($identifier, -1)
        );
    }

    /**
     * Quote an alias
     *
     * @param string $alias The alias string to quote
     * @param string $q The quote char
     * @return string
     */
    protected function quoteAlias(string $alias): string
    {
        $q = $this->qi;
        return $q . trim($alias, $q) . $q;
    }

    /**
     * Escape a value, when appliable, for SQL expression
     *
     * @param mixed $value The target identifier (column or alias)
     * @deprecated
     */
    protected function escapeValue($value): string
    {
        if (null === $value) {
            return 'NULL';
        }

        return addcslashes((string)$value, "\x00\n\r\\'\"\x1a");
    }

    /**
     * Quote a value, when appliable, for SQL expression
     *
     * @param mixed $value The target identifier (column or alias)
     * @deprecated
     */
    protected function quoteValue($value): string
    {
        if (null === $value) {
            return 'NULL';
        }

        $q = $this->qv;

        return "{$q}{$this->escapeValue($value)}{$q}";
    }

    /**
     * Create a statement string marker for a given value
     *
     * @staticvar int $i Increment counter
     *
     * @param mixed $value The parameter value
     * @param int $type The optional forced parameter type
     *
     * @return string
     */
    protected function createNamedParam($value, int $type = null): string
    {
        //return $this->createPositionalParam($value, $param_type);

        $marker = ":" . self::$index . "";

        $this->setParam($marker, $value, $type);

        self::$index = self::$index < self::MAX_INDEX ? (self::$index + 1) : 1;

        return $marker;
    }

    /**
     * Create a statement string marker for a given value
     *
     * @staticvar int $i Increment counter
     *
     * @param mixed $value The parameter value
     * @param int $type The optional forced parameter type
     *
     * @return string
     */
    protected function createPositionalParam($value, int $type = null): string
    {
        $this->setParam(self::$index, $value, $type);

        self::$index = self::$index < self::MAX_INDEX ? (self::$index + 1) : 1;

        return '?';
    }

    /**
     * Add a parameter and its type to the internal list
     *
     * @param int|string $key
     * @param mixed $value
     * @param int $type
     */
    private function setParam($key, $value, int $type = null)
    {
        $this->params[$key] = $value;

        if (!isset($param_type)) {
            if (is_null($value)) {
                $param_type = PDO::PARAM_NULL;
            } elseif (is_int($value) || is_bool($value)) {
                $param_type = PDO::PARAM_INT;
            } else {
                $param_type = PDO::PARAM_STR;
            }
        }

        $this->params_types[$key] = $param_type;
    }

    public function __clone()
    {
        unset($this->sql);
    }
}

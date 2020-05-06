<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use PDO;

/**
 * The default ANSI SQL Driver
 */
class Driver
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
     * @param Db $db the database connection, if any
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
        if (!empty($this->ql) && $this->ql === substr($identifier, 0, 1)) {
            return true;
        }
        if (!empty($this->qr) && $this->qr === substr($identifier, -1)) {
            return true;
        }
        return false;
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
     * @param mixed $value The target identifier (column or alias)
     * @deprecated
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
            } catch (Exception $ex) {
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
}

<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

/**
 * Interface Driver
 */
abstract class Driver
{
    /**
     * @var string The quote left char for identifiers/aliases, default is ANSI '"'
     */
    protected $ql = '"';

    /**
     * @var string The quote right char for identifiers/aliases, default is ANSI '"'
     */
    protected $qr = '"';

    /**
     * @var string The quote char for values
     */
    protected $qv = "'";

    /**
     * Quote a yet unquoted identifier that represents a table column
     *
     * @param string $identifier The target identifier (column, table.column, t.column)
     * @return string
     */
    public function quoteIdentifier(string $identifier): string
    {
        if ($identifier === '*') {
            return $identifier;
        }

        if ($this->isQuoted($identifier)) {
            return $identifier;
        }

        $ql = $this->ql;
        $qr = $this->qr;

        $identifier = ltrim(rtrim($identifier, $qr), $ql);

        if (false === strpos($identifier, '.')) {
            return "{$ql}{$identifier}{$qr}";
        }

        $quoted = $ql . str_replace('.', "{$qr}.{$ql}", $identifier) . $qr;
        $quoted = str_replace("{$ql}*{$qr}", '*', $quoted); // unquote the sql asterisk

        return $quoted;
    }

    private function isQuoted(string $identifier)
    {
        return ($this->ql !== ''
            && $this->ql === substr($identifier, 0, 1)
            && $this->ql === substr($identifier, -1)
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
        $ql = $this->ql;
        $qr = $this->qr;

        return $ql. ltrim(rtrim($identifier, $qr), $ql) . $qr;
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
        if (is_int($value)) {
            return (string)$value;
        }
        if (is_bool($value)) {
            return (string)(int)$value;
        }

        if (!is_string($value)) {
            $value = (string)$value;
        }

        return "{$this->ql}{$this->escape($value)}{$this->qr}";
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
}

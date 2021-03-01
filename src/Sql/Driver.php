<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Driver\Ansi;
use P3\Db\Sql\DriverInterface;
use PDO;
use ReflectionClass;
use RuntimeException;

use function addcslashes;
use function is_bool;
use function is_int;
use function is_string;
use function ltrim;
use function rtrim;
use function setlocale;
use function sprintf;
use function str_replace;
use function strlen;
use function strpos;
use function strtolower;
use function substr;

use const LC_NUMERIC;

/**
 * A SQL Driver provides methods for quoting identifier, aliases, values and
 * escaping strings. It may provide additional SQL-string building methods for
 * database backends that follows less-strictly the SQL standards.
 *
 * @property-read string $ql The left quote char, if any
 * @property-read string $qr The right quote char, if any
 * @property-read string $qv The value quote char, if any
 * @property-read string $name The driver short name
 */
abstract class Driver implements DriverInterface
{
    /**
     * @var PDO|null
     */
    protected $pdo;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string The left quote char for identifiers/aliases, default is ANSI '"'
     */
    protected $ql;

    /**
     * @var string The right quote char for identifiers/aliases, default is ANSI '"'
     */
    protected $qr;

    /**
     * @var string ql-concat-qr, used only for checks
     */
    protected $qlr;

    /**
     * @var string The quote char for values, default is single-quote char "'"
     */
    protected $qv;

    /**
     * @var self The basic singleton ansi driver instance
     */
    private static $ansi;

    protected const ESCAPE_CHARLIST = "\x00\n\r\\'\"\x1a";

    /**
     * @param PDO|null $pdo the database connection, if any
     * @param string $ql left-quote char
     * @param string $qr right-quote char
     * @param string $qv quote char for values
     */
    public function __construct(
        ?PDO $pdo,
        string $ql,
        string $qr,
        string $qv
    ) {
        if (isset($pdo)) {
            $this->pdo = $pdo;
        }

        self::assertValidQuotingChar($ql, 'left');
        self::assertValidQuotingChar($qr, 'right');
        self::assertValidQuotingChar($qv, 'value');

        $this->ql = $ql;
        $this->qr = $qr;
        $this->qv = $qv;

        $this->qlr = "{$ql}{$qr}";
    }

    protected static function assertValidQuotingChar(string &$qc, string $type)
    {
        $qc = trim($qc);
        if ('' === $qc || strlen($qc) !== 1 || '\\' === $qc) {
            throw new InvalidArgumentException(
                "invalid {$type}-quoting char `{$qc}` provided!"
            );
        }
    }

    public function getName(): string
    {
        return $this->name ?? $this->name = strtolower(
            (new ReflectionClass($this))->getShortName()
        );
    }

    public function setPDO(PDO $pdo)
    {
        $this->pdo = $pdo;
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

        return $ql . ltrim(rtrim($alias, $qr), $ql) . $qr;
    }

    /**
     * Quote a value, when needed, to be used in a SQL expression
     *
     * Potentially dangerous: always prefer parameter binding
     *
     * @param mixed $value
     * @return string
     * @throws RuntimeException
     */
    public function quoteValue($value): string
    {
        if (null === $value) {
            return Sql::NULL;
        }

        if (is_int($value)) {
            return (string)$value;
        }

        if (is_bool($value)) {
            return (string)(int)$value;
        }

        if (is_float($value)) {
            // make sure we use the dot '.' as decimal separator
            $lc_numeric = setlocale(LC_NUMERIC, 0);
            setlocale(LC_NUMERIC, 'C');
            $str_value = (string)$value;
            setlocale(LC_NUMERIC, $lc_numeric);
            return $str_value;
        }

        if (!is_string($value)) {
            $value = (string)$value;
        }

        if (!isset($this->pdo)) {
            throw new RuntimeException(
                "Unable to quote a string value: missng PDO instance!"
            );
        }

        $quoted_value = $this->pdo->quote($value, PDO::PARAM_STR);

        if ($quoted_value === false) {
            throw new RuntimeException(sprintf(
                "Quoting non supported by pdo-driver `%s`!",
                $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)
            ));
        }

        return $quoted_value;
    }

    /**
     * Escape a string without quoting for usage as a SQL value
     *
     * Potentially dangerous: always prefer parameter binding
     *
     * @param string $value
     * @deprecated Use parameter binding
     */
    protected function escape(string $value): string
    {
        return addcslashes($value, static::ESCAPE_CHARLIST);
    }

    /**
     * Return the basic ANSI driver shared instance
     *
     * @return self
     */
    public static function ansi(): self
    {
        return self::$ansi ?? self::$ansi = new Ansi();
    }

    public function __get(string $name)
    {
        if ($name === 'ql') {
            return $this->ql;
        }
        if ($name === 'qr') {
            return $this->qr;
        }
        if ($name === 'qv') {
            return $this->qv;
        }
        if ($name === 'name') {
            return $this->name ?? $this->getName();
        }
    }
}

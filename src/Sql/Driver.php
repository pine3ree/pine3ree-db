<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Sql;
use P3\Db\Sql\Driver\Ansi;
use P3\Db\Sql\DriverInterface;
use P3\Db\Sql\ElementInterface;
use P3\Db\Sql\Params;
use P3\Db\Sql\Statement\Select;
use PDO;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use P3\Db\Exception\RuntimeException;

use function addcslashes;
use function get_class;
use function gettype;
use function is_bool;
use function is_float;
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
     * The driver pdo name
     *
     * @var string
     */
    protected $name;

    /**
     * The left quote char for identifiers/aliases, default is ANSI '"'
     *
     * @var string
     */
    protected $ql;

    /**
     * The right quote char for identifiers/aliases, default is ANSI '"'
     *
     * @var string
     */
    protected $qr;

    /**
     * The quote char for values, default is single-quote char "'"
     *
     * @var string
     */
    protected $qv;

    /**
     * The basic singleton ansi driver instance
     *
     * @var self
     */
    private static $ansi;

    protected const ESCAPE_CHARLIST = "\x00\n\r\\'\"\x1a";

    /**
     * Reflection methods cache
     *
     * @var array
     */
    protected static $rm = [];

    /**
     *  A map of supported pdo-driver-name to sql-driver-class
     *
     * @const array<string, string>
     */
    public const SUPPORTED = [
        'mysql'  => Driver\MySql::class,
        'sqlite' => Driver\Sqlite::class,
        'pgsql'  => Driver\PgSql::class,
        'oci'    => Driver\Oci::class,
        'sqlsrv' => Driver\SqlSrv::class,
    ];

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

    /**
     * @param string $driver The PDO driver name ('mysql', 'sqlite', ...)
     * @return bool
     */
    public static function isSupported(string $driver): bool
    {
        return !empty(self::SUPPORTED[$driver]);
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

    public function quoteIdentifier(string $identifier): string
    {
        if ($identifier === '*' || $this->isQuoted($identifier)) {
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

    public function quoteAlias(string $alias): string
    {
        $ql = $this->ql;
        $qr = $this->qr;

        return $ql . ltrim(rtrim($alias, $qr), $ql) . $qr;
    }

    public function quoteValue($value): string
    {
        if (null === $value) {
            return Sql::NULL;
        }

        if (is_int($value)) {
            return (string)$value;
        }

        if (is_bool($value)) {
            return $value ? Sql::TRUE : Sql::FALSE;
        }

        if (is_float($value)) {
            // make sure we use the dot '.' as decimal separator
            $lc_numeric = setlocale(LC_NUMERIC, 0);
            if ($lc_numeric === 'C') {
                return (string)$value;
            }

            setlocale(LC_NUMERIC, 'C');
            $str_value = (string)$value;
            setlocale(LC_NUMERIC, $lc_numeric);

            return $str_value;
        }

        if (is_string($value)) {
            return $this->quoteStringValue($value);
        }

        // other types are nt supported
        $type = gettype($value);
        throw new InvalidArgumentException(
            "Cannot quote non scalar value. Supported types are: null, bool,"
            . " inti, float and string, `{$type}` provided!"
        );
    }

    /**
     * @param string $value
     * @return string
     * @throws RuntimeException
     */
    protected function quoteStringValue(string $value): string
    {
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
     * @deprecated Only used as a fallback in the ANSI pdo-less driver
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

    protected function generateSelectSQL(Select $select, Params $params, bool $pretty = false): string
    {
        return $this->call($select, 'generateSQL', $this, $params, $pretty);
    }

    /**
     * Invoke a sql element private method
     *
     * @param ElementInterface $element
     * @param string $methodName
     * @param array ...$args
     * @return mixed
     * @throws RuntimeException
     */
    protected function call(ElementInterface $element, string $methodName, ...$args)
    {
        $fqcn = get_class($element);
        $key = "{$fqcn}::{$methodName}" ;

        $method = self::$rm[$key] ?? null;

        if (!isset($method)) {
            try {
                $method = new ReflectionMethod($fqcn, $methodName);
                $method->setAccessible(true);
                self::$rm[$key] = $method;
            } catch (ReflectionException $ex) {
                self::$rm[$key] = $method = false;
            }
        }

        if (false === $method) {
            throw new RuntimeException(
                "Call to undefined method `{$methodName}`!"
            );
        }

        return $method->invokeArgs($element, $args);
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

        throw new RuntimeException(
            "Undefined property {$name}!"
        );
    }
}

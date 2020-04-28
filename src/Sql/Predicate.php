<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use InvalidArgumentException;
use PDO;

/**
 * Class Predicate
 */
abstract class Predicate implements \JsonSerializable
{
    protected $sql;
    protected $params = [];
    protected $params_types = [];

    protected const MAX_INDEX = 999999;

    public function getParams(): array
    {
        return $this->params;
    }
    public function getParamsTypes(): array
    {
        return $this->params_types;
    }

    abstract public function getSQL(): string;

    public function __toString(): string
    {
        return $this->getSQL();
    }

    protected function isEmptySQL($sql): bool
    {
        return !is_string($sql) || '' === trim($sql);
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
        //return $this->createPositionalParam($value, $param_type);
        static $i = 1;

        $marker = ":_np{$i}";

        $this->setParam($marker, $value, $param_type);

        $i = $i < self::MAX_INDEX ? ($i + 1) : 1;

        return $marker;
    }

    protected function createPositionalParam($value, int $param_type = null): string
    {
        static $index = 1;

        $this->setParam($index, $value, $param_type);

        $index = $index < self::MAX_INDEX ? ($index + 1) : 1;

        return '?';
    }

    private function setParam($key, $value, int $param_type = null)
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

    protected static function assertValidIdentifier($identifier)
    {
        if (!is_string($identifier) && ! $identifier instanceof Literal) {
            throw new InvalidArgumentException(sprintf(
                "A predicate identifier must be either a string or an SQL Literal, '%s' provided!!",
                is_object($identifier) ? get_class($identifier) : gettype($identifier)
            ));
        }
    }
    public function jsonSerialize()
    {
        return [
            'class'  => static::class,
            'getSQL' => $this->getSQL(),
        ];
    }
}

<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

/**
 * Class Predicate
 */
abstract class Predicate implements \JsonSerializable
{
    protected $params = [];
    protected $params_types = [];

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

    public function jsonSerialize()
    {
        return [
            'class'  => static::class,
            'getSQL' => $this->getSQL(),
        ];
    }
}

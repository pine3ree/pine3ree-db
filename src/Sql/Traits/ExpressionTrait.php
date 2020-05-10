<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Traits;

use P3\Db\Sql\Driver;
use function preg_replace;

/**
 * Provide the getSQL() method for parametric sql-expression
 */
trait ExpressionTrait
{
    /**
     * @var string The "{name}"-parametrized SQL-expression
     */
    private $expression;

    public function getSQL(Driver $driver = null): string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        if (empty($this->params)) {
            return $this->sql = $this->expression ;
        }

        $driver = $driver ?? Driver::ansi();

        // rewrite the `{name}` markers
        $sql = $this->expression;

        // replace unquoted ? markers with freshly generated named markers
        $qv = $driver->qv;
        $regexp = "/([^\\{$qv}]|^)\?([^\\{$qv}]|$)/";

        $params = $params_types = [];

        foreach ($this->params as $key => $value) {
            $marker = $this->createNamedParam($value);
            $sql = str_replace("{{$key}}", $marker, $sql);
            $params[$marker] = $value;
            $params_types[$marker] = $this->params_types[$index];
        }

        $this->params = $params;
        $this->params_types = $params_types;

        return $this->sql = $sql;
    }
}

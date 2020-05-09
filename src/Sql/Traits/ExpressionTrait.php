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
     * @var string The "?"-parametrized SQL-expression
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

        // rewrite the `?` markers
        $sql = $this->expression;

        $params = [];
        $params_types = [];
        foreach ($this->params as $index => $value) {
            $marker = $this->createNamedParam($value);
            $sql = preg_replace('/\?/', $marker, $sql, 1);
            $params[$marker] = $value;
            $params_types[$marker] = $this->params_types[$index];
        }

        $this->params = $params;
        $this->params_types = $params_types;

        return $this->sql = $sql;
    }
}

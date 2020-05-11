<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Driver;

use PDO;
use P3\Db\Sql\Driver;

/**
 * ANSI-SQL sql-driver
 */
class Ansi extends Driver
{
    public function __construct()
    {
        parent::__construct(null, '"', '"', "'");
    }

    public function setPDO(PDO $pdo)
    {
        // do not use PDO for ANSI-SQL;
    }

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

        return "{$this->qv}{$this->escape($value)}{$this->qv}";
    }
}

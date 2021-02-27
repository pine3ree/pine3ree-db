<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Driver;

use P3\Db\Sql;
use P3\Db\Sql\Driver;
use P3\Db\Sql\Statement\Select;
use PDO;

use const PHP_INT_MAX;

/**
 * MySql sql-driver
 */
class MySql extends Driver
{
    public function __construct(PDO $pdo = null)
    {
        parent::__construct($pdo, '`', '`', "'");
    }
}

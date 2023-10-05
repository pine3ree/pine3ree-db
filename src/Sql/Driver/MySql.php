<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace pine3ree\Db\Sql\Driver;

use pine3ree\Db\Sql\Driver;
use PDO;

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

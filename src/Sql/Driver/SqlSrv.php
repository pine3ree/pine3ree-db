<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Driver;

use PDO;
use P3\Db\Sql\Driver;

/**
 * SqlSrv sql-driver
 */
class SqlSrv extends Driver
{
    public function __construct(PDO $pdo = null)
    {
        parent::__construct($pdo, '[', ']', "'");
    }
}

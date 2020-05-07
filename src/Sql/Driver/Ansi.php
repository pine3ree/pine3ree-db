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
        // do notthing;
    }
}

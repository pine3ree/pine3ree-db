<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Driver;

use P3\Db\Sql\Driver;

/**
 * Sqlite sql-driver
 */
class Sqlite extends Driver
{
    public function __construct()
    {
        parent::__construct('"', '"', "'");
    }
}

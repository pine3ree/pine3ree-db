<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Driver;

use P3\Db\Sql\Driver;

/**
 * Postgre sql-driver
 */
class PgSql extends Driver
{
    public function __construct()
    {
        parent::__construct('"', '"', "'");
    }
}

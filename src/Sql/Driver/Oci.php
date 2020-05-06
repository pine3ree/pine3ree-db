<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Driver;

use P3\Db\Sql\Driver;

/**
 * Oci sql-driver
 */
class Oci extends Driver
{
    public function __construct()
    {
        parent::__construct('`', '`', "'");
    }

    public function getLimitSQL(int $limit = null, int $offset = null): string
    {
        return '';
    }
}

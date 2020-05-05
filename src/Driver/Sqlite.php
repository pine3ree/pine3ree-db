<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Driver;

use P3\Db\Driver;

/**
 * Class MySql
 */
class Sqlite extends Driver
{
    protected $qr = '"';
    protected $ql = '"';
}

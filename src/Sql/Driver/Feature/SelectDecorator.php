<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql\Driver\Feature;

use P3\Db\Sql\Params;
use P3\Db\Sql\Statement\Select;

/**
 * Interface SelectDecorator
 */
interface SelectDecorator
{
    /**
     * Decorate a sql Select statement object in order to provide unsupported feature.
     *
     * If no decoration is needed it just returns the original select
     *
     * @param Select $select
     * @return Select
     */
    public function decorateSelect(Select $select, Params $params): Select;
}

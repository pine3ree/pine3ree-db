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
     * Decorate a sql Select statement object in order to provide unsupported features
     *
     * If no decoration is needed it just returns the original select
     *
     * @param Select $select The original SQL select statement object
     * @param Params $params The parameters collector
     * @return Select The decorated SQL select statement or the original object
     *      if no decoration was needed
     */
    public function decorateSelect(Select $select, Params $params): Select;
}

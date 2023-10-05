<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace pine3ree\Db\Sql\Driver\Feature;

use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Statement\Select;

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

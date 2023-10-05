<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

declare(strict_types=1);

namespace pine3ree\Db\Sql\Driver;

use PDO;
use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\Driver\Feature\LimitSqlProvider;
use pine3ree\Db\Sql\Params;
use pine3ree\Db\Sql\Statement\Select;

use const PHP_INT_MAX;

/**
 * The default ANSI SQL Driver
 */
class Ansi extends Driver implements LimitSqlProvider
{
    public function __construct()
    {
        parent::__construct(null, '"', '"', "'");
    }

    public function setPDO(PDO $pdo): void
    {
        // do not use PDO for ANSI-SQL;
    }

    public function quoteStringValue(string $value): string
    {
        return "'{$this->escape($value)}'";
    }

    /**
     * ANSI SQL does not support LIMIT/OFFSET, return a placeholder string.
     */
    public function getLimitSQL(Select $select, Params $params): string
    {
        $limit  = $select->limit;
        $offset = $select->offset;

        if (!isset($limit) && (int)$offset === 0) {
            return '';
        }

        if (isset($limit)) {
            $sql = Sql::LIMIT . " {$limit}";
        }

        $offset = (int)$offset;
        if ($offset > 0) {
            if (!isset($sql)) {
                $sql = Sql::LIMIT . " " . PHP_INT_MAX;
            }
            $sql .= " " . Sql::OFFSET . " {$offset}";
        }

        return isset($sql) ? "[{$sql}]" : "";
    }
}

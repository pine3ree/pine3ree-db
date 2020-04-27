<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db;

/**
 * Class Sql
 */
class Sql
{
    /**
     * DQL/DML query types
     */
    public const SELECT = 'SELECT';
    public const INSERT = 'INSERT';
    public const UPDATE = 'UPDATE';
    public const DELETE = 'DELETE';

    /**
     * Clauses
     */
    public const WHERE    = 'WHERE';
    public const JOIN     = 'JOIN';
    public const ON       = 'ON';
    public const GROUP_BY = 'GROUP BY';
    public const HAVING   = 'HAVING';
    public const ORDER_BY = 'ORDER BY';
    public const LIMIT    = 'LIMIT';
    public const OFFSET   = 'OFFSET';

    /**
     * Join clause types
     */
    const JOIN_AUTO          = '';
    const JOIN_INNER         = 'INNER';
    const JOIN_CROSS         = 'CROSS';
    const JOIN_LEFT          = 'LEFT';
    const JOIN_RIGHT         = 'RIGHT';
    const JOIN_STRAIGHT      = 'STRAIGHT_JOIN';
    const JOIN_NATURAL       = 'NATURAL';
    const JOIN_NATURAL_LEFT  = 'NATURAL LEFT';
    const JOIN_NATURAL_RIGHT = 'NATURAL RIGHT';

    /**
     * ORDER BY directions
     */
    public const ASC  = 'ASC';
    public const DESC = 'DESC';

    /**
     * Comparison operators...
     */
    public const EQUAL              = '=';
    public const NOT_EQUAL          = '!=';
    public const LESS_GREATER       = '<>'; // NOT EQUAL ANSI-SQL
    public const LESS_THAN          = '<';
    public const LESS_THAN_EQUAL    = '<=';
    public const GREATER_THAN_EQUAL = '>=';
    public const GREATER_THAN       = '>';
    /**
     * ...and their short aliases
     */
    public const EQ  = self::EQUAL;
    public const NEQ = self::NOT_EQUAL;
    public const NE  = self::LESS_GREATER;
    public const LT  = self::LESS_THAN;
    public const LTE = self::LESS_THAN_EQUAL;
    public const GTE = self::GREATER_THAN_EQUAL;
    public const GT  = self::GREATER_THAN;

    /**
     * Other conditional operators
     */
    public const BETWEEN     = 'BETWEEN';
    public const NOT_BETWEEN = 'NOT BETWEEN';
    public const IN          = 'IN';
    public const NOT_IN      = 'NOT IN';
    public const LIKE        = 'LIKE';
    public const NOT_LIKE    = 'NOT LIKE';

    /**
     * Boolean operators
     */
    public const AND = 'AND';
    public const OR  = 'OR';

    public const STAR = '*';

    /**
     * Quantifiers
     */
    public const DISTINCT = 'DISTINCT';
    public const ALL = 'ALL';
}

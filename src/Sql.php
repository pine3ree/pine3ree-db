<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace pine3ree\Db;

use pine3ree\Db\Sql\Alias;
use pine3ree\Db\Sql\Expression;
use pine3ree\Db\Sql\Identifier;
use pine3ree\Db\Sql\Literal;
use pine3ree\Db\Sql\Statement\Delete;
use pine3ree\Db\Sql\Statement\Insert;
use pine3ree\Db\Sql\Statement\Select;
use pine3ree\Db\Sql\Statement\Update;

use function strtoupper;

/**
 * Class Sql exposes common SQL constants and utility methods
 */
class Sql
{
    /*
     * DQL/DML statements
     */
    public const SELECT = 'SELECT';
    public const INSERT = 'INSERT';
    public const INSERT_IGNORE = 'INSERT IGNORE';
    public const UPDATE = 'UPDATE';
    public const DELETE = 'DELETE';

    public const DML_STATEMENTS = [
        self::SELECT => self::SELECT,
        self::INSERT => self::INSERT,
        self::UPDATE => self::UPDATE,
        self::DELETE => self::DELETE,
    ];

    /*
     * DQL/DML keywords
     */
    public const ASTERISK = '*';
    public const FROM     = 'FROM';
    public const AS       = 'AS';
    public const INTO     = 'INTO';
    public const VALUES   = 'VALUES';
    public const SET      = 'SET';

    /**
     * DDL statements
     */
    public const CREATE = 'CREATE';
    public const ALTER  = 'ALTER';
    public const DROP   = 'DROP';

    public const DDL_STATEMENTS = [
        self::CREATE => self::CREATE,
        self::ALTER  => self::ALTER,
        self::DROP   => self::DROP,
    ];

    /*
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

    public const CLAUSES = [
        self::WHERE    => self::WHERE,
        self::JOIN     => self::JOIN,
        self::ON       => self::ON,
        self::GROUP_BY => self::GROUP_BY,
        self::HAVING   => self::HAVING,
        self::ORDER_BY => self::ORDER_BY,
        self::LIMIT    => self::LIMIT,
        self::OFFSET   => self::OFFSET,
    ];

    /*
     * Join clause types
     */
    public const JOIN_AUTO          = '';
    public const JOIN_INNER         = 'INNER';
    public const JOIN_CROSS         = 'CROSS';
    public const JOIN_LEFT          = 'LEFT';
    public const JOIN_RIGHT         = 'RIGHT';
    public const JOIN_STRAIGHT      = 'STRAIGHT';
    public const JOIN_NATURAL       = 'NATURAL';
    public const JOIN_NATURAL_LEFT  = 'NATURAL LEFT';
    public const JOIN_NATURAL_RIGHT = 'NATURAL RIGHT';
    public const USING              = 'USING';

    public const JOIN_TYPES = [
        self::JOIN_AUTO          => self::JOIN_AUTO,
        self::JOIN_INNER         => self::JOIN_INNER,
        self::JOIN_CROSS         => self::JOIN_CROSS,
        self::JOIN_LEFT          => self::JOIN_LEFT,
        self::JOIN_RIGHT         => self::JOIN_RIGHT,
        self::JOIN_STRAIGHT      => self::JOIN_STRAIGHT,
        self::JOIN_NATURAL       => self::JOIN_NATURAL,
        self::JOIN_NATURAL_LEFT  => self::JOIN_NATURAL_LEFT,
        self::JOIN_NATURAL_RIGHT => self::JOIN_NATURAL_RIGHT,
    ];

    /*
     * ORDER BY directions
     */
    public const ASC  = 'ASC';
    public const DESC = 'DESC';

    public const SORT = [
        self::ASC  => self::ASC,
        self::DESC => self::DESC,
    ];


    /*
     * SQL values
     */
    public const NULL    = 'NULL';
    public const TRUE    = 'TRUE';
    public const FALSE   = 'FALSE';
    public const UNKNOWN = 'UNKNOWN';

    /*
     * Comparison operators...
     */
    public const EQUAL              = '=';
    public const NOT_EQUAL          = '!=';
    public const NOT_EQUAL_ANSI     = '<>'; // NOT EQUAL ANSI-SQL
    public const LESS_THAN          = '<';
    public const LESS_THAN_EQUAL    = '<=';
    public const GREATER_THAN_EQUAL = '>=';
    public const GREATER_THAN       = '>';
    //...and their short-name aliases
    public const EQ  = self::EQUAL;
    public const NEQ = self::NOT_EQUAL;
    public const NE  = self::NOT_EQUAL_ANSI;
    public const LT  = self::LESS_THAN;
    public const LTE = self::LESS_THAN_EQUAL;
    public const GTE = self::GREATER_THAN_EQUAL;
    public const GT  = self::GREATER_THAN;

    // Valid comparison operators excluding null/boolean
    public const COMPARISON_OPERATORS = [
        self::EQ  => self::EQUAL,
        self::NEQ => self::NOT_EQUAL,
        self::NE  => self::NOT_EQUAL_ANSI,
        self::LT  => self::LESS_THAN,
        self::LTE => self::LESS_THAN_EQUAL,
        self::GTE => self::GREATER_THAN_EQUAL,
        self::GT  => self::GREATER_THAN,
    ];

    /*
     * null/boolean comparison operators
     */
    public const IS     = 'IS';
    public const IS_NOT = 'IS NOT';

    // Valid null/bolean operators
    public const BOOLEAN_OPERATORS = [
        self::IS     => self::IS,
        self::IS_NOT => self::IS_NOT,
    ];

    /*
     * Other conditional operators
     */
    public const BETWEEN     = 'BETWEEN';
    public const NOT_BETWEEN = 'NOT BETWEEN';
    public const EXISTS      = 'EXISTS';
    public const NOT_EXISTS  = 'NOT EXISTS';
    public const IN          = 'IN';
    public const NOT_IN      = 'NOT IN';
    public const LIKE        = 'LIKE';
    public const NOT_LIKE    = 'NOT LIKE';

    // Valid conditional operators
    public const CONDITIONAL_OPERATORS = [
        self::BETWEEN     => self::BETWEEN,
        self::NOT_BETWEEN => self::NOT_BETWEEN,
        self::EXISTS      => self::EXISTS,
        self::NOT_EXISTS  => self::NOT_EXISTS,
        self::IN          => self::IN,
        self::NOT_IN      => self::NOT_IN,
        self::LIKE        => self::LIKE,
        self::NOT_LIKE    => self::NOT_LIKE,
    ];

    // Valid operators excluding boolean operators
    public const OPERATORS
        = self::COMPARISON_OPERATORS
        + self::BOOLEAN_OPERATORS
        + self::CONDITIONAL_OPERATORS;

    /*
     * Logical operators
     */
    public const AND = 'AND';
    public const OR  = 'OR';
    public const XOR = 'XOR';
    public const NOT = 'NOT';

    /*
     * Quantifiers
     */
    public const DISTINCT = 'DISTINCT';
    public const ALL      = 'ALL';
    public const SOME     = 'SOME';
    public const ANY      = 'ANY';

    public const QUANTIFIERS = [
        self::DISTINCT => self::DISTINCT,
        self::ALL      => self::ALL,
    ];

    /*
     * COMBINED-SETS
     */
    public const UNION     = 'UNION';
    public const UNION_ALL = 'UNION ALL';
    public const INTERSECT = 'INTERSECT';

    public const SETS_COMBINATIONS = [
        self::UNION     => self::UNION,
        self::INTERSECT => self::INTERSECT,
    ];

    /*
     * FUNCTIONS
     */
    public const ESCAPE = 'ESCAPE';
    public const COALESCE = 'COALESCE';
    // AGGREGATE FUNCTIONS
    public const COUNT = 'COUNT';
    public const SUM = 'SUM';
    public const MIN = 'MIN';
    public const MAX = 'MAX';
    public const AVG = 'AVG';

    public static function isValidJoin(string $join): bool
    {
        return isset(self::JOIN_TYPES[strtoupper($join)]);
    }

    public static function isSupportedOperator(string $operator): bool
    {
        return isset(self::OPERATORS[strtoupper($operator)]);
    }

    /**
     * Create and return a new Select sql-statement
     *
     * @param string|string[]|Expression|Expression[]|Identifier|Identifier[]|Literal|Literal[]|Select|Select[] $columns
     *      One or many column names, Identifiers, Literals, Expressions or sub-select statements or the sql-asterisk
     * @psalm-param string|array<int|string, string|Expression|Identifier|Literal|Select> $columns
     * @param string|Select|null $from The db-table name or a sub-select statement
     * @param string|null $alias The db-table alias
     * @return Select
     */
    public static function select($columns = Sql::ASTERISK, $from = null, string $alias = null): Select
    {
        return new Select($columns, $from, $alias);
    }

    /**
     * Create and return a new Insert sql-statement
     *
     * @param string|null $table
     * @return Insert
     */
    public static function insert(string $table = null): Insert
    {
        return new Insert($table);
    }

    /**
     * Create and return a new Update sql-statement
     *
     * @param string|null $table
     * @return Update
     */
    public static function update(string $table = null): Update
    {
        return new Update($table);
    }

    /**
     * Create and return a new Delete sql-statement
     *
     * @param string|null $table The db-table to delete from
     * @return Delete
     */
    public static function delete($table = null): Delete
    {
        return new Delete($table);
    }

    public static function literal(string $literal): Literal
    {
        return new Literal($literal);
    }

    public static function expression(string $expression, array $substitutions = []): Expression
    {
        return new Expression($expression, $substitutions);
    }

    /**
     * @alias of self::expression()
     */
    public static function expr(string $expression, array $substitutions = []): Expression
    {
        return self::expression($expression, $substitutions);
    }

    /**
     * Create a sql-identifier
     *
     * @param string $identifier
     * @return Identifier
     */
    public static function identifier(string $identifier): Identifier
    {
        return new Identifier($identifier);
    }

    /**
     * Create a sql-alias
     *
     * @param string $alias
     * @return Alias
     */
    public static function alias(string $alias): Alias
    {
        return new Alias($alias);
    }
}

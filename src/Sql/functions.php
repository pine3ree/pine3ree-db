<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Sql;

use P3\Db\Sql;
use P3\Db\Sql\Alias;
use P3\Db\Sql\Expression;
use P3\Db\Sql\Identifier;
use P3\Db\Sql\Literal;

/**
 * @see Alias
 * @see Sql::alias()
 *
 * @param string $alias
 * @return Alias
 */
function alias(string $alias): Alias
{
    return new Alias($alias);
}

/**
 * @see Expression
 * @see Sql::expression()
 *
 * @param string $expression
 * @param array $substitutions
 * @return Expression
 */
function expression(string $expression, array $substitutions = []): Expression
{
    return new Expression($expression, $substitutions);
}

/**
 * @see Identifier
 * @see Sql::identifier()
 *
 * @param string $identifier
 * @return Identifier
 */
function identifier(string $identifier): Identifier
{
    return new Identifier($identifier);
}

/**
 * @see Literal
 * @see Sql::literal()
 *
 * @param string $literal
 * @return Literal
 */
function literal(string $literal): Literal
{
    return new Literal($literal);
}

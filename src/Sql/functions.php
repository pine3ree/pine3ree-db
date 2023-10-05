<?php

/**
 * @package pine3ree-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace pine3ree\Db\Sql;

use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Alias;
use pine3ree\Db\Sql\Expression;
use pine3ree\Db\Sql\Identifier;
use pine3ree\Db\Sql\Literal;

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
 * @psalm-param array<string, mixed> $substitutions
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

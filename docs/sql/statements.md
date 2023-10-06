## pine3ree\Db\Sql\Statement

The SQL statement classes abstract full sql statements with named placeholder markers
in place of the actual parameter values. They are composed of simpler SQL elements
such as identifiers, aliases, expressions, predicates and clauses.

Statement are actually sql-statement builders providing methods for adding the
inner elements they are composed of.

Supported statements are `Select` for DQL, and `Insert`, `Update`, `Delete` for DML,
reflecting the previously examined database command classes. The sql-building methods
used in a command instance are proxies to corresponding methods of the composed
sql-statement instance.

### pine3ree\Db\Sql\Statement\Select
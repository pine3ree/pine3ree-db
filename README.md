# P3\Db

A tiny PDO database abstraction layer wrapper - WIP

## TODO

- Separate tests into unit-testing and integration tests. Add integration tests
- Decide on OCI driver identifier quoting

## DISCARDED
- Rewrite `ConditionaClause` by extending `Predicate\Set` and creating Clause interface and trait
  Reason: composition makes more sense despite requiring more code for implementing proxy methods
          to the composed predicate-set

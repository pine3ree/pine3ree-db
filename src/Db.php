<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db;

use PDO;
use PDOStatement;

/**
 * Class Db
 */
class Db
{
    /**
     * @var PDO
     */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function fetchByPK(string $table, $pk): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE id = :id");
        $result = $stmt->execute(['id' => $pk]);

        if ($result === false) {
            return null;
        }

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function fetchOne(string $table, $where = null, $order = null): ?array
    {
        $select = $this->select()->from($table);

        if (isset($where)) {
            $select->where($where);
        }
        if (isset($order)) {
            $select->orderBy($order);
        }
        $select->limit(1);

        $stmt = $this->pdo->prepare($select->getSQL());

        $params = $select->getParams();
        $types  = $select->getParamsTypes();

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, $types[$key] ?? PDO::PARAM_STR);
        }

        $result = $stmt->execute();

        if ($result === false) {
            return null;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $stmt->closeCursor();

        return $row;
    }

    public function fetchAll(
        string $table,
        $where = null,
        $order = null,
        int $limit = null,
        int $offset = null
    ): ?array {
        $select = $this->select()->from($table);

        if (isset($where)) {
            $select->where($where);
        }
        if (isset($order)) {
            $select->orderBy($order);
        }
        if (isset($limit)) {
            $select->limit($limit)->offset((int)$offset);
        }

        $stmt = $this->pdo->prepare($select->getSQL());

        $params = $select->getParams();
        $types  = $select->getParamsTypes();

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, $types[$key] ?? PDO::PARAM_STR);
        }

        $result = $stmt->execute();

        if ($result === false) {
            return [];
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
//
//
//        $sql = "SELECT * FROM `{$table}`";
//
//        $params = [];
//
//        if (!empty($where)) {
//            $sql .= $this->buildWhereSQL($where, $params);
//        }
//
//        if (!empty($order)) {
//            $sql .= " ORDER BY {$order}";
//        }
//
//        if (!empty($limit)) {
//            $sql .= " LIMIT " . max(0, $limit);
//        }
//        if (!empty($offset)) {
//            $sql .= " OFFSET " . max(0, $offset);
//        }
//
//        $stmt = $this->pdo->prepare($sql);
//        $result = $stmt->execute($params);
//
//        if ($result === false) {
//            return [];
//        }
//
//        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(string $table, array $rows): ?bool
    {
        if (empty($data)) {
            return null;
        }

        $columns = array_keys($data);

        $sql_columns = "(`" . implode('`, `', $columns) . "`)";
        $sql_markers = "(:" . implode(', :', $columns) . ")";

        $sql = "INSERT INTO `{$table}` {$columns} VALUES {$sql_markers}";

        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($data);

        if ($result === false) {
            return false;
        }

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return null;
    }

    public function insertRow(string $table, array $row): ?bool
    {
        foreach ($row as $column => $value) {
            if (!is_null($value) && !is_scalar($value)) {
                unset($row[$column]);
            }
        }

        if (empty($row)) {
            return null;
        }

        $columns = array_keys($row);

        $sql_columns = "(`" . implode('`, `', $columns) . "`)";
        $sql_markers = "(:" . implode(', :', $columns) . ")";

        $sql = "INSERT INTO `{$table}` {$sql_columns} VALUES {$sql_markers}";

        $stmt = $this->pdo->prepare($sql);

        foreach ($row as $column => $value) {
            if (is_int($value)) {
               $stmt->bindValue(":{$column}", $value, PDO::PARAM_INT);
            } elseif (is_bool($value)) {
                $stmt->bindValue(":{$column}", $value, PDO::PARAM_BOOL);
            } elseif (is_null($value)) {
                $stmt->bindValue(":{$column}", $value, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(":{$column}", $value, PDO::PARAM_STR);
            }
        }

        $result = $stmt->execute();

        if ($result === false) {
            return false;
        }

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return null;
    }

//    public function updateRow(string $table, array $data, $where = null): ?bool
//    {
//        if (empty($data)) {
//            return null;
//        }
//
//        $params = [];
//        $set_sql = $this->buildSetSQL($data, $params);
//        if (empty($set_sql)) {
//            return null;
//        }
//
//        $sql = "UPDATE `{$table}` {$set_sql}";
//        if (!empty($where)) {
//            $sql .= $this->buildWhereSQL($where, $params);
//        }
//        $sql .= " LIMIT 1";
//
//        $stmt = $this->pdo->prepare($sql);
//        $result = $stmt->execute($params);
//
//        if ($result === false) {
//            return false;
//        }
//
//        if ($stmt->rowCount() > 0) {
//            return true;
//        }
//
//        return null;
//    }

    /**
     *
     * @param string|array|null $table
     * @param array|null $data
     * @param array|null $where
     * @return \P3\Db\Query\Update|false|int
     */
    public function update(string $table = null, array $data = null, $where = null)
    {
        $update = new Query\Update($table);

        if (func_num_args() < 2 || !isset($data)) {
            return $update;
        }

        if (empty($data)) {
            return null;
        }

        $update->set($data)->where($where);

        $sql = $update->getSQL();

        if (empty($sql)) {
            return false;
        }

        $stmt = $this->pdo->prepare($sql);

        $params = $update->getParams();
        $types  = $update->getParamsTypes();

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, $types[$key] ?? PDO::PARAM_STR);
        }

        $result = $stmt->execute();

        if ($result === false) {
            return false;
        }

        return $stmt->rowCount();
    }

    /**
     *
     * @param string|array|null $table
     * @param array|string $columns
     * @return \P3\Db\Query\Select
     */
    public function select($columns = Query\Select::ANY)
    {
        return new Query\Select($columns);
    }

    private function buildWhereSQL($where, array &$params = null): string
    {
        if (empty($where)) {
            return '';
        }

        static $i = 0;

        if (is_array($where)) {
            $where_sqls = [];
            foreach ($where as $col => $value) {
                $i += 1;
                if (is_string($col)) {
                    if (is_null($value)) {
                        $where_sqls[] = "`{$col}` IS NULL";
                    } else {
                        $where_sqls[] = "`{$col}` = :{$col}{$i}";
                        $params[":{$col}{$i}"] = $value;
                    }
                } elseif (is_numeric($col) && is_string($value)) {
                    $where_sqls[] = "{$value}";
                } elseif (is_scalar($value)) {
                    $where_sqls[] = "`{$col}` = :{$col}{$i}";
                    $params[":{$col}{$i}"] = is_bool($value) ? (int)$value : $value;
                } elseif (is_null($value)) {
                    $where_sqls[] = "`{$col}` IS NULL";
                } elseif (is_array($value) && count($value) == 3) {
                    $c = $value[0];
                    $o = $value[1];
                    $v = $value[2];
                    if ($o === 'BETWEEN') {
                        if (!is_array($v) || count($v) !== 2) {
                            continue;
                        }
                        $where_sqls[] = "{$c} BETWEEN :{$c}{$i}_min AND :{$c}{$i}_max";
                        $params[":{$c}{$i}_min"] = $v[0];
                        $params[":{$c}{$i}_max"] = $v[1];
                    } elseif ($o === 'IN') {
                        if (is_null($v)) {
                            $where_sqls[] = "`{$c}` IN (NULL)";
                        } elseif (is_scalar($v)) {
                            $where_sqls[] = "`{$c}` IN (:{$c}{$i})";
                            $params[":{$c}{$i}"] = $v;
                        } elseif (is_array($v)) {
                            $in_sqls = [];
                            foreach ($v as $vin) {
                                if (is_null($vin)) {
                                    $in_sqls[] = "NULL";
                                } else {
                                    $in_sqls[] = ":{$c}{$i}";
                                    $params[":{$c}{$i}"] = $vin;
                                }
                                $i += 1;
                            }
                            $where_sqls[] = "`{$c}` IN (" . implode(',', $in_sqls) . ")";
                        }
                    } else {
                        $where_sqls[] = "`{$c}` {$o} :{$c}{$i}";
                        $params[":{$c}{$i}"] = $v;
                    }
                }
            }

            $where_sql = "(" . implode(") AND (", $where_sqls) . ")";

            return " WHERE {$where_sql}";
        }

        if (is_string($where)) {
            return " WHERE {$where}";
        }

        if (is_int($where)) {
            return " WHERE id = '{$where}'";
        }

        return '';
    }
}

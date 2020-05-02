<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db;

use PDO;
use PDOStatement;
use P3\Db\Sql\Statement;
use P3\Db\Sql\Statement\Delete;
use P3\Db\Sql\Statement\Insert;
use P3\Db\Sql\Statement\Select;
use P3\Db\Sql\Statement\Update;
use P3\Db\Sql\Condition\Where;

use function func_num_args;
use function is_array;
use function is_int;

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

    public function fetchByPK(string $table, $pk_value, $pk_column = 'id'): ?array
    {
        if (is_int($pk_value)) {
            $stmt = $this->pdo->query(
                "SELECT * FROM `{$table}` WHERE `{$pk_column}` = '{$pk_value}'"
            );
        } else {
           $stmt = $this->pdo->prepare(
                "SELECT * FROM `{$table}` WHERE `{$pk_column}` = :id"
            );
        }

        if ($stmt === false) {
            return null;
        }

        $result = $stmt->execute([$pk_column => $pk_value]);

        if ($result === false) {
            return null;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return is_array($row) ? $row : null;
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

        $stmt = $this->prepare($select, true);
        if (false === $stmt || false === $stmt->execute()) {
            return null;
        }
        if (false === $stmt->execute()) {
            return null;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return is_array($row) ? $row : null;
    }

    public function fetchAll(
        string $table,
        $where = null,
        $order = null,
        int $limit = null,
        int $offset = null
    ): array {
        $select = $this->select()->from($table);

        if (isset($where)) {
            $select->where($where);
        }
        if (isset($order)) {
            $select->orderBy($order);
        }
        if (isset($limit)) {
            $select->limit($limit);
            if (isset($limit)) {
                $select->offset($offset);
            }
        }

        $stmt = $this->prepare($select, true);
        if (false === $stmt || false === $stmt->execute()) {
            return false;
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $rows;
    }

    /**
     * Create and return a new Select statement
     *
     * @param array|string $columns
     * @param string|array|null $table
     * @return P3\Sql\Statement\Select
     */
    public function select($columns = Sql::ASTERISK, $table = null): Select
    {
        return new Select($columns, $table);
    }

    /**
     * Create an Insert statement and either return it run it if additional
     * arguments are provided
     *
     * @param string|null $table
     * @param array[] $rows
     * @return Insert|false|int
     */
    public function insert(string $table = null, array $rows = null)
    {
        if (func_num_args() < 2) {
            return new Insert($table);
        }

        $insert = new Insert();
        $insert
            ->into($table)
            ->rows($rows);

        $stmt = $this->prepare($insert, true);
        if (false === $stmt || false === $stmt->execute()) {
            return false;
        }

        return $stmt->rowCount();
    }

    /**
     * Insert a new row/record into the a db-table
     *
     * @param string $table
     * @param array $row
     *
     * @return bool
     */
    public function insertRow(string $table, array $row): bool
    {
        $insert = new Insert();
        $insert
            ->into($table)
            ->row($row);

        $stmt = $this->prepare($insert, true);
        if (false === $stmt || false === $stmt->execute()) {
            return false;
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * Create an Update statement and either return it run it if additional
     * arguments are provided
     *
     * @param string|array|null $table
     * @param array|null $data
     * @param string|array|Predicate|Where $where
     *
     * @return Update|false|int
     */
    public function update(string $table = null, array $data = null, $where = null)
    {
        $update = new Update($table);

        if (func_num_args() < 2 || !isset($data)) {
            return $update;
        }

        $update
            ->set($data)
            ->where($where);

        $stmt = $this->prepare($update, true);
        if (false === $stmt || false === $stmt->execute()) {
            return false;
        }

        return $stmt->rowCount();
    }

    /**
     * Create and return a new Select statement
     *
     * @param string|array|null $table
     * @param string|array|Predicate|Where $where
     * @return Delete|bool|int
     */
    public function delete($from = null, $where = null): Select
    {
        $delete = Delete($from);

        if (func_num_args() < 2 || !isset($where)) {
            return $delete;
        }

        $delete->where($where);

        $stmt = $this->prepare($delete, true);
        if (false === $stmt || false === $stmt->execute()) {
            return false;
        }

        return $stmt->rowCount();
    }

    /**
     * Prepare a Statement and optionally bind its values returning the
     * prepared/binded PDOStatement
     *
     * @param \P3\Db\Statement $statement
     * @return PDOStatement|false
     */
    public function prepare(Statement $statement, bool $bind = false)
    {
        $stmt = $this->pdo->prepare($statement->getSQL());

        if ($bind && $stmt instanceof PDOStatement) {
            $params = $statement->getParams();
            $ptypes = $statement->getParamsTypes();
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, $ptypes[$key] ?? PDO::PARAM_STR);
            }
        }

        return $stmt;
    }

    /**
     * Prepare and execute a Statement returning the result of PDOStatement::execute()
     *
     * @param \P3\Db\Statement $statement
     * @return bool
     */
    public function executeStatement(Statement $statement): bool
    {
        $stmt = $this->prepare($statement, true);

        if ($stmt instanceof PDOStatement) {
            return $stmt->execute();
        }

        return false;
    }
}

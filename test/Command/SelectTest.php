<?php

/**
 * @package     pine3ree-db
 * @subpackage  pine3ree-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace pine3ree\DbTest\Command;

use pine3ree\Db\Command\Select;
use pine3ree\Db\Db;
use pine3ree\Db\Exception\InvalidArgumentException;
use pine3ree\Db\Exception\RuntimeException;
use pine3ree\Db\Sql;
use pine3ree\Db\Sql\Driver;
use pine3ree\Db\Sql\Statement;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use ReflectionProperty;
use stdClass;

use function is_int;
use function is_object;

// @codingStandardsIgnoreStart
if (trait_exists(ProphecyTrait::class)) {
    class SelectTestBase extends TestCase
    {
       use ProphecyTrait;
    }
} else {
    class SelectTestBase extends TestCase
    {
    }
}
// @codingStandardsIgnoreEnd

// @codingStandardsIgnoreLine
class SelectTest extends SelectTestBase
{
    /** @var ObjectProphecy|Db */
    private $db;

    /** @var ObjectProphecy|Driver\Mysql */
    private $driver;

    /** @var ObjectProphecy|PDO */
    private $pdo;

    /** @var ObjectProphecy|PDOStatement */
    private $pdoStatement;

    private const RESULT_ROW_1 = [
        'id' => 1,
        'username' => 'username-1',
        'email' => 'email1@example.com',
    ];
    private const RESULT_ROW_2 = [
        'id' => 1,
        'username' => 'username-1',
        'email' => 'email1@example.com',
    ];
    private const RESULT_ROW_3 = [
        'id' => 1,
        'username' => 'username-1',
        'email' => 'email1@example.com',
    ];

    private const RESULT_ROWS = [
        self::RESULT_ROW_1,
        self::RESULT_ROW_2,
        self::RESULT_ROW_3,
    ];

    private const RESULT_ROWS_BY_EMAIL = [
        self::RESULT_ROW_1['email'] => self::RESULT_ROW_1,
        self::RESULT_ROW_1['email'] => self::RESULT_ROW_2,
        self::RESULT_ROW_1['email'] => self::RESULT_ROW_3,
    ];

    public function setUp(): void
    {
        $this->pdo = $this->prophesize(PDO::class);
        $this->pdoStatement = $this->prophesize(PDOStatement::class);

        $this->pdoStatement
            ->execute()
            ->willReturn($this->returnSelf());

        $this->pdoStatement
            ->closeCursor()
            ->willReturn(null);

        $this->driver = $this->prophesize(Driver\MySql::class);

        $this->db = $this->prophesize(Db::class);
        $this->db
            ->getDriver(true)
            ->willReturn(new Driver\MySql($this->pdo->reveal()));
    }

    public function tearDown(): void
    {
        $this->db = null;
        $this->pdo = null;
        $this->driver = null;
    }

    private function buildResultRow($id, $fetchMode, $fetchClassOrObject = null)
    {
        if (PDO::FETCH_ASSOC === $fetchMode) {
            return [
                'id' => $id,
                'username' => "username{$id}",
                'email' => "email{$id}@example.com",
            ];
        }

        if (PDO::FETCH_NUM === $fetchMode) {
            return [
                0 => $id,
                1 => "username{$id}",
                2 => "email{$id}@example.com",
            ];
        }

        if (PDO::FETCH_BOTH === $fetchMode) {
            return [
                'id' => $id,
                'username' => "username{$id}",
                'email' => "email{$id}@example.com",
                0 => $id,
                1 => "username{$id}",
                2 => "email{$id}@example.com",
            ];
        }

        if (PDO::FETCH_OBJ === $fetchMode
            || PDO::FETCH_CLASS === $fetchMode
            || PDO::FETCH_LAZY === $fetchMode
        ) {
            $obj = new stdClass();
            $obj->id = $id;
            $obj->username = "username{$id}";
            $obj->email = "email{$id}@example.com";

            return $obj;
        }

        if (PDO::FETCH_INTO === $fetchMode) {
            $obj = clone $fetchClassOrObject;
            $obj->id = $id;
            $obj->username = "username{$id}";
            $obj->email = "email{$id}@example.com";

            return $obj;
        }
    }

    private function buildResultRows($fetchMode): array
    {
        $rows = [];
        for ($id = 1; $id < 10; $id++) {
            $rows[] = $this->buildResultRow($id, $fetchMode);
        }

        return $rows;
    }

    private function indexResultRowsBy($property, array $rows): array
    {
        $idexed = [];
        foreach ($rows as $row) {
            $indexBy = is_object($row) ? $row->{$property} : $row[$property];
            $idexed[$indexBy] = $row;
        }

        return $idexed;
    }

    protected function createSelectCommand(Db &$dbMock = null): Select
    {
        $db = $this->prophesize(Db::class);

        $db->getDriver(true)
            ->willReturn(new Driver\MySql($this->pdo->reveal()));

        $db->getDriver(false)
            ->willReturn(new Driver\MySql());

        $db->getDriver()
            ->willReturn(new Driver\MySql());

        $select = new Select($db->reveal());

        $db->prepare($select->sqlStatement, true)
            ->willReturn($this->pdoStatement->reveal());

        $db->prepare($select->sqlStatement, true, null)
            ->willReturn($this->pdoStatement->reveal());

        $dbMock = $db->reveal();

        return $select;
    }

    public function testGetSqlStatement()
    {
        $select = $this->createSelectCommand($db);
        self::assertInstanceOf(Statement\Select::class, $select->getSqlStatement());
    }

    public function testGetSql()
    {
        $select = $this->createSelectCommand($db);
        $select->from('user', 'u');

        self::assertStringMatchesFormat("SELECT `u`.*%wFROM `user` `u`", $select->getSql());

        self::assertSame(
            $select->getSqlStatement()->getSQL($db->getDriver()),
            $select->getSql()
        );

        self::assertSame(
            $select->getSqlStatement()->getSQL($db->getDriver()),
            $select->getSql()
        );
    }

    public function testQuantifier()
    {
        $select = $this->createSelectCommand($db);
        $select->quantifier(Sql::DISTINCT)->from('user', 'u');
        self::assertStringMatchesFormat("SELECT DISTINCT `u`.*%wFROM `user` `u`", $select->getSql());
    }

    public function testDistinct()
    {
        $select = $this->createSelectCommand($db);
        $select->distinct()->from('user', 'u');
        self::assertStringMatchesFormat("SELECT DISTINCT `u`.*%wFROM `user` `u`", $select->getSql());
    }

    public function testColumns()
    {
        $select = $this->createSelectCommand($db);
        $select->columns(['id', 'email'])->from('user', 'u');
        self::assertStringMatchesFormat("SELECT `u`.`id`, `u`.`email`%wFROM `user` `u`", $select->getSql());
    }

    public function testColumn()
    {
        $select = $this->createSelectCommand($db);
        $select
            ->column('id')
            ->column('email')
            ->column('logged_at', 'lastAccess')
            ->from('user', 'u');

        self::assertStringMatchesFormat(
            "SELECT"
            . " `u`.`id`,"
            . " `u`.`email`,"
            . " `u`.`logged_at` AS `lastAccess`"
            . "%wFROM `user` `u`",
            $select->getSql()
        );
    }

    public function testAggregateMethods()
    {
        $select = $this->createSelectCommand($db);
        $select->count()->from('product');
        self::assertStringMatchesFormat(
            "SELECT COUNT(*)%wFROM `product`",
            $select->getSql()
        );

        $select = $this->createSelectCommand($db);
        $select->count('id')->from('product');
        self::assertStringMatchesFormat(
            "SELECT COUNT(id)%wFROM `product`",
            $select->getSql()
        );

        $select = $this->createSelectCommand($db);
        $select->sum('price')->from('product');
        self::assertStringMatchesFormat(
            "SELECT SUM(price)%wFROM `product`",
            $select->getSql()
        );

        $select = $this->createSelectCommand($db);
        $select->min('price')->from('product');
        self::assertStringMatchesFormat(
            "SELECT MIN(price)%wFROM `product`",
            $select->getSql()
        );

        $select = $this->createSelectCommand($db);
        $select->max('price')->from('product');
        self::assertStringMatchesFormat(
            "SELECT MAX(price)%wFROM `product`",
            $select->getSql()
        );

        $select = $this->createSelectCommand($db);
        $select->avg('price')->from('product');
        self::assertStringMatchesFormat(
            "SELECT AVG(price)%wFROM `product`",
            $select->getSql()
        );

        $select = $this->createSelectCommand($db);
        $select->aggregate('SOMEFUNCTION', 'price')->from('product');
        self::assertStringMatchesFormat(
            "SELECT SOMEFUNCTION(price)%wFROM `product`",
            $select->getSql()
        );
    }

    /**
     * @dataProvider provideJoinOptions
     */
    public function testJoins($joinMethod, $joinSQL)
    {
        $joinSQL = trim("{$joinSQL} JOIN");

        $select = $this->createSelectCommand($db);
        $select->from('user', 'u');
        $select->{$joinMethod}('session', 's', "s.user_id = u.id");

        self::assertStringMatchesFormat(
            "SELECT `u`.*"
            . "%wFROM `user` `u`"
            . "%w{$joinSQL} `session` `s` ON (`s`.user_id = `u`.id)",
            $select->getSql()
        );

        self::assertSame(
            $select->sqlStatement->getSQL($db->getDriver()),
            $select->getSql()
        );
    }

    public function testAddJoin()
    {

        $select = $this->createSelectCommand($db);
        $select->from('user', 'u');

        $join = new Sql\Clause\Join(Sql::JOIN_INNER, 'session', 's', "s.user_id = u.id");
        $select->addJoin($join);

        self::assertStringMatchesFormat(
            "SELECT `u`.*"
            . "%wFROM `user` `u`"
            . "%wINNER JOIN `session` `s` ON (`s`.user_id = `u`.id)",
            $select->getSql()
        );
        self::assertSame(
            $select->sqlStatement->getSQL($db->getDriver()),
            $select->getSql()
        );
    }

    public function provideJoinOptions(): array
    {
        return [
            ['join', Sql::JOIN_AUTO],
            ['crossJoin', Sql::JOIN_CROSS],
            ['innerJoin', Sql::JOIN_INNER],
            ['leftJoin', Sql::JOIN_LEFT],
            ['naturalJoin', Sql::JOIN_NATURAL],
            ['naturalLeftJoin', Sql::JOIN_NATURAL_LEFT],
            ['naturalRightJoin', Sql::JOIN_NATURAL_RIGHT],
            ['rightJoin', Sql::JOIN_RIGHT],
            ['straightjoin', Sql::JOIN_STRAIGHT],
        ];
    }

    public function testWhereClause()
    {
        $select = $this->createSelectCommand($db);
        $select->from('user');
        $select->where->greaterThan('id', 42);
        self::assertStringMatchesFormat(
            "SELECT *%wFROM `user`%wWHERE `id` > :gt%d",
            $select->getSql()
        );

        $select = $this->createSelectCommand($db);
        $select->from('user');
        $select->where("id > 42");
        self::assertStringMatchesFormat(
            "SELECT *%wFROM `user`%wWHERE id > 42",
            $select->getSql()
        );
    }

    public function testHavingClause()
    {
        $select = $this->createSelectCommand($db);
        $select->from('user');
        $select->having->lessThan(Sql::literal("(id * 10)"), 42);
        self::assertStringMatchesFormat(
            "SELECT *%wFROM `user`%wHAVING (id * 10) < :lt%d",
            $select->getSql()
        );

        $select = $this->createSelectCommand($db);
        $select->from('user');
        $select->having("(id*10) <= 42");
        self::assertStringMatchesFormat(
            "SELECT *%wFROM `user`%wHAVING (id*10) <= 42",
            $select->getSql()
        );
    }

    public function testGroupByClause()
    {
        $select = $this->createSelectCommand($db);
        $select->from('user')->groupBy('type_id');
        self::assertStringMatchesFormat(
            "SELECT *%wFROM `user`%wGROUP BY `type_id`",
            $select->getSql()
        );

        $select->groupBy('category_id', true);
        self::assertStringMatchesFormat(
            "SELECT *%wFROM `user`%wGROUP BY `category_id`",
            $select->getSql()
        );
    }

    public function testOrderByClause()
    {
        $select = $this->createSelectCommand($db);
        $select->from('user')->orderBy('id');
        self::assertStringMatchesFormat(
            "SELECT *%wFROM `user`%wORDER BY `id` ASC",
            $select->getSql()
        );

        $select = $this->createSelectCommand($db);
        $select->from('user')->orderBy('id', 'DESC');
        self::assertStringMatchesFormat(
            "SELECT *%wFROM `user`%wORDER BY `id` DESC",
            $select->getSql()
        );
    }

    public function testLimitClause()
    {
        $select = $this->createSelectCommand($db);
        $select->from('user')->limit(10);

        self::assertStringMatchesFormat(
            "SELECT *%wFROM `user`%wLIMIT :limit%d",
            $select->getSql()
        );
    }

    public function testOffsetClause()
    {
        $select = $this->createSelectCommand($db);
        $select->from('user')->offset(100);

        self::assertStringMatchesFormat(
            "SELECT *%wFROM `user`%wLIMIT " . PHP_INT_MAX . " OFFSET :offset%d",
            $select->getSql()
        );
    }

    public function testUnionClause()
    {
        $union = (new Statement\Select())->from('session');

        $select = $this->createSelectCommand($db);
        $select->union($union)->from('user');

        self::assertStringMatchesFormat(
            "SELECT *%wFROM `user`%wUNION%wSELECT *%wFROM `session`",
            $select->getSql()
        );
    }

    public function testIntersectClause()
    {
        $intersect = (new Statement\Select())->from('session');

        $select = $this->createSelectCommand($db);
        $select->intersect($intersect)->from('user');

        self::assertStringMatchesFormat(
            "SELECT *%wFROM `user`%wINTERSECT%wSELECT *%wFROM `session`",
            $select->getSql()
        );
    }

    public function testIndexByMethodSetTheInternalProperty()
    {
        $select = $this->createSelectCommand($db);
        $select->from('user')->indexBy('email');

        $indexByProp = new ReflectionProperty(Select::class, 'indexBy');
        $indexByProp->setAccessible(true);

        self::assertSame('email', $indexByProp->getValue($select));
    }

    public function testFetchAll()
    {
        $select = $this->createSelectCommand($db);
        $select->from('user');

        $assocRows = $this->buildResultRows(PDO::FETCH_ASSOC);
        $numRows   = $this->buildResultRows(PDO::FETCH_NUM);
        $bothRows  = $this->buildResultRows(PDO::FETCH_BOTH);
        $objRows   = $this->buildResultRows(PDO::FETCH_OBJ);
        $classRows = $this->buildResultRows(PDO::FETCH_CLASS);

        $this->pdoStatement->fetchAll(PDO::FETCH_ASSOC)->willReturn($assocRows);
        $this->pdoStatement->fetchAll()->willReturn($assocRows);
        self::assertSame($assocRows, $select->fetchAll());
        self::assertSame($assocRows, $select->fetchAll(PDO::FETCH_ASSOC));

        $this->pdoStatement->fetchAll(PDO::FETCH_NUM)->willReturn($numRows);
        self::assertSame($numRows, $select->fetchAll(PDO::FETCH_NUM));

        $this->pdoStatement->fetchAll(PDO::FETCH_BOTH)->willReturn($bothRows);
        self::assertSame($bothRows, $select->fetchAll(PDO::FETCH_BOTH));

        $this->pdoStatement->fetchAll(PDO::FETCH_OBJ)->willReturn($objRows);
        self::assertSame($objRows, $select->fetchAll(PDO::FETCH_OBJ));

        $this->pdoStatement->fetchAll(PDO::FETCH_CLASS)->willReturn($classRows);
        self::assertSame($classRows, $select->fetchAll(PDO::FETCH_CLASS));
    }

    public function testFetchAllOnPdoStatementExecutionFailureReturnsEmptyArray()
    {
        $select = $this->createSelectCommand($db);
        $select->from('user');

        $this->pdoStatement->execute()->willReturn(false);

        self::assertSame([], $select->fetchAll());
    }

    public function testFetchAllOnEmptyPdoStatementFetchAllReturnsNoRows()
    {
        $select = $this->createSelectCommand($db);
        $select->from('user');

        $this->pdoStatement->fetchAll()->willReturn([]);
        $this->pdoStatement->fetchAll(PDO::FETCH_ASSOC)->willReturn([]);
        $this->pdoStatement->fetchAll(PDO::FETCH_OBJ)->willReturn([]);

        self::assertSame([], $select->fetchAll());
    }

    public function testFetchAllIndexedByEmail()
    {
        $select = $this->createSelectCommand($db);
        $select->from('user')->indexBy('email');

        $assocRows = $this->buildResultRows(PDO::FETCH_ASSOC);
        $bothRows  = $this->buildResultRows(PDO::FETCH_BOTH);
        $objRows   = $this->buildResultRows(PDO::FETCH_OBJ);
        $classRows = $this->buildResultRows(PDO::FETCH_CLASS);

        $assocRowsByEmail = $this->indexResultRowsBy('email', $assocRows);
        $bothRowsByEmail  = $this->indexResultRowsBy('email', $bothRows);
        $objRowsByEmail   = $this->indexResultRowsBy('email', $objRows);
        $classRowsByEmail = $this->indexResultRowsBy('email', $classRows);

        $this->pdoStatement->fetchAll()->willReturn($bothRows);
        $this->pdoStatement->fetchAll(PDO::FETCH_ASSOC)->willReturn($assocRows);
        self::assertSame($assocRowsByEmail, $select->fetchAll());
        self::assertSame($assocRowsByEmail, $select->fetchAll(PDO::FETCH_ASSOC));

        $this->pdoStatement->fetchAll(PDO::FETCH_BOTH)->willReturn($bothRows);
        self::assertSame($bothRowsByEmail, $select->fetchAll(PDO::FETCH_BOTH));

        $this->pdoStatement->fetchAll(PDO::FETCH_OBJ)->willReturn($objRows);
        self::assertSame($objRowsByEmail, $select->fetchAll(PDO::FETCH_OBJ));

        $this->pdoStatement->fetchAll(PDO::FETCH_CLASS)->willReturn($classRows);
        self::assertSame($classRowsByEmail, $select->fetchAll(PDO::FETCH_CLASS));
    }

    /**
     * @dataProvider provideFetchAllModes
     */
    public function testFetchAllIndexedByInvalidIndexWillRaiseException(?int $fetchMode)
    {
        $select = $this->createSelectCommand($db);
        $select->from('user')->indexBy('nonexistent');

        $rows = $this->buildResultRows($fetchMode ?? PDO::FETCH_ASSOC);

        $this->pdoStatement->fetchAll()->willReturn($rows);
        $this->pdoStatement->fetchAll($fetchMode ?? PDO::FETCH_ASSOC)->willReturn($rows);

        $this->expectException(RuntimeException::class);

        if (is_int($fetchMode)) {
            $select->fetchAll($fetchMode);
        } else {
            $select->fetchAll();
        }
    }

    public function provideFetchAllModes()
    {
        return[
          [null],
          [PDO::FETCH_ASSOC],
          [PDO::FETCH_BOTH],
          [PDO::FETCH_OBJ],
          [PDO::FETCH_CLASS],
        ];
    }

    public function testFetchOneOnPdoStatementExecutionFailureReturnsNull()
    {
        $select = $this->createSelectCommand($db);
        $select->from('user');

        $this->pdoStatement->execute()->willReturn(false);

        self::assertNull($select->fetchOne());
    }

    /**
     * @dataProvider provideFetchOneModes
     */
    public function testFetchOne(?int $fetchMode, $fetchClassOrObject = null)
    {
        $select = $this->createSelectCommand($db);
        $select->from('user');

        $row = $this->buildResultRow(1, $fetchMode ?? PDO::FETCH_ASSOC, $fetchClassOrObject);

        if ($fetchMode === PDO::FETCH_CLASS
            || $fetchMode === (PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE)
        ) {
            $this->pdoStatement->setFetchMode($fetchMode, $fetchClassOrObject, [])->willReturn(null);
            $this->pdoStatement->fetch()->willReturn($row);
        } else {
            $this->pdoStatement->fetch($fetchMode ?? PDO::FETCH_ASSOC)->willReturn($row);
        }

        $actual = is_int($fetchMode)
           ? $select->fetchOne($fetchMode, $fetchClassOrObject)
           : $select->fetchOne();

        self::assertSame($row, $actual);
    }

    public function provideFetchOneModes()
    {
        return[
          [null, null],
          [PDO::FETCH_ASSOC, null],
          [PDO::FETCH_BOTH, null],
          [PDO::FETCH_OBJ, stdClass::class],
          [PDO::FETCH_CLASS, stdClass::class],
          [PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, stdClass::class],
          [PDO::FETCH_LAZY, stdClass::class],
          [PDO::FETCH_INTO, new stdClass()],
        ];
    }

    /**
     * @dataProvider provideFetchOneInvalidFetchArgs
     */
    public function testFetchOneWithInvalidFetchClassOrObjectWillRaiseException($fetchMode, $fetchClassOrObject)
    {
        $select = $this->createSelectCommand($db);
        $select->from('user');

        $this->expectException(InvalidArgumentException::class);

        $actual = is_int($fetchMode)
           ? $select->fetchOne($fetchMode, $fetchClassOrObject)
           : $select->fetchOne();
    }

    public function provideFetchOneInvalidFetchArgs()
    {
        return[
            [PDO::FETCH_CLASS, 1],
            [PDO::FETCH_CLASS, null],
            [PDO::FETCH_CLASS, new stdClass()],
            [PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, null],
            [PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, 1],
            [PDO::FETCH_INTO, null],
            [PDO::FETCH_INTO, 42],
            [PDO::FETCH_INTO, 'a-string'],
        ];
    }

    public function testFetchScalar()
    {
        $select = $this->createSelectCommand($db);
        $select->from('user');

        $row = $this->buildResultRow(1, PDO::FETCH_ASSOC);
        $this->pdoStatement->fetch(PDO::FETCH_ASSOC)->willReturn($row);

        self::assertSame('username1', $select->fetchScalar('username'));
        self::assertSame('email1@example.com', $select->fetchScalar('email'));
        self::assertNull($select->fetchScalar('nonexistent'));

        $this->pdoStatement->execute()->willReturn(false);
        self::assertNull($select->fetchScalar('nonexistent'));
    }

    public function testFetchColumn()
    {
        $select = $this->createSelectCommand($db);
        $select->from('user');

        $row = $this->buildResultRow(1, PDO::FETCH_NUM);

        $this->pdoStatement->fetchColumn(0)->willReturn($row[0]);
        $this->pdoStatement->fetchColumn(1)->willReturn($row[1]);
        $this->pdoStatement->fetchColumn(2)->willReturn($row[2]);
        $this->pdoStatement->fetchColumn(3)->willReturn($row[3] ?? null);

        self::assertSame(1, $select->fetchColumn(0));
        self::assertSame('username1', $select->fetchColumn(1));
        self::assertSame('email1@example.com', $select->fetchColumn(2));
        self::assertNull($select->fetchColumn(3));

        $this->pdoStatement->execute()->willReturn(false);
        self::assertNull($select->fetchColumn(0));
    }
}

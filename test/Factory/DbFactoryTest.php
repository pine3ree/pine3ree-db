<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Factory;

use InvalidArgumentException;
use P3\Db\Db;
use P3\Db\Factory\DbFactory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class DbFactoryTest extends TestCase
{
    /** @var ContainerInterface */
    private $container;

    /** @var DbFactory */
    private $factory;

    private const DB_CONFIG = [
        'driver'   => 'mysql',
        'hostname' => 'localhost',
        'database' => 'testdb',
        'port'     => 3306,
        'username' => 'testuser',
        'password' => 'secret',
        'charset'  => 'utf8',
    ];

    private const PDO_CONFIG = [
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'dbname'   => 'testdb',
        'port'     => 3306,
        'username' => 'testuser',
        'passwd'   => 'secret',
        'charset'  => 'utf8',
    ];

    private const SQLSRV_CONFIG = [
        'driver'   => 'sqlsrv',
        'server'   => 'localhost',
        'database' => 'testdb',
        'port'     => 3306,
        'username' => 'testuser',
        'passwd'   => 'secret',
        'charset'  => 'utf8',
        'app'      => 'testapp',
        'encrypt'  => true,
        'failover_partner' => '10.0.0.123',
        'login_timeout' => 15,
        'mars'     => true,
        'quoted_id' => true,
        'connection_pooling' => false,
        'wsid'     => 'primary',
        'trace_file' => '/var/log/sqlsrv-trace.log',
        'trace_on' => true,
        'transaction_isolation' => 1,
        'trust_server_certificate' => true,
    ];

    public function setUp(): void
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->container->has('config')->willReturn(true);

        $this->factory = new DbFactory();
    }

    public function tearDown()
    {
        $this->factory = null;
        $this->container = null;
    }

    public function testFactoryWithDsnProvidedInConfig()
    {
        $this->container->get('config')
            ->willReturn(
                [
                    'pdo' => [
                        'dsn' => 'mysql:host=localhost;port=3306;dbname=test',
                        'username' => 'testuser',
                        'password' => 'secret',
                    ]
                ]
            );

        $db = ($this->factory)($this->container->reveal());

        self::assertInstanceOf(Db::class, $db);
    }

    public function testFactoryWithFullDbConfig()
    {
        $this->container
            ->get('config')
            ->willReturn([
                'db' => self::DB_CONFIG,
            ]);

        $db = ($this->factory)($this->container->reveal());
        self::assertInstanceOf(Db::class, $db);
    }

    public function testThatMissingConfigRisesException()
    {
        $dbConfig = self::DB_CONFIG;
        unset($dbConfig['driver']);

        $this->container->get('config')
            ->willReturn(
                [
                ]
            );

        $this->expectException(InvalidArgumentException::class);
        $db = ($this->factory)($this->container->reveal());
    }

    public function testThatMissingDriverConfigKeyRisesException()
    {
        $dbConfig = self::DB_CONFIG;
        unset($dbConfig['driver']);

        $this->container->get('config')
            ->willReturn(
                [
                    'db' => $dbConfig,
                ]
            );

        $this->expectException(InvalidArgumentException::class);
        $db = ($this->factory)($this->container->reveal());
    }

    public function testThatUnsupportedDriverConfigKeyRisesException()
    {
        $dbConfig = self::DB_CONFIG;
        $dbConfig['driver'] = 'myfancydbdriver';

        $this->container->get('config')
            ->willReturn(
                [
                    'db' => $dbConfig,
                ]
            );

        $this->expectException(InvalidArgumentException::class);
        $db = ($this->factory)($this->container->reveal());
    }

    public function testThatMissingDatabaseConfigKeyRisesException()
    {
        $dbConfig = self::DB_CONFIG;
        unset($dbConfig['database'], $dbConfig['dbname']);

        $this->container->get('config')
            ->willReturn(
                [
                    'db' => $dbConfig,
                ]
            );

        $this->expectException(InvalidArgumentException::class);
        $db = ($this->factory)($this->container->reveal());
    }

    public function testWithMysqlDriverAndUnixSocketConfigKey()
    {
        $dbConfig = self::DB_CONFIG;
        $dbConfig['unix_socket'] = '/var/run/mysql.sock';

        $this->container->get('config')
            ->willReturn(
                [
                    'db' => $dbConfig,
                ]
            );

        $db = ($this->factory)($this->container->reveal());
        self::assertInstanceOf(Db::class, $db);
    }

    public function testWithPgsqlDriverConfigKey()
    {
        $dbConfig = self::DB_CONFIG;
        $dbConfig['driver'] = 'pgsql';

        $this->container->get('config')
            ->willReturn(
                [
                    'db' => $dbConfig,
                ]
            );

        $db = ($this->factory)($this->container->reveal());
        self::assertInstanceOf(Db::class, $db);
    }

    public function testWithSqliteDriverConfigKey()
    {
        $dbConfig = self::DB_CONFIG;
        $dbConfig['driver'] = 'sqlite';
        $dbConfig['dbname'] = ':memory:';

        $this->container->get('config')
            ->willReturn(
                [
                    'db' => $dbConfig,
                ]
            );

        $db = ($this->factory)($this->container->reveal());
        self::assertInstanceOf(Db::class, $db);
    }

    public function testWithOciDriverConfigKey()
    {
        $dbConfig = self::DB_CONFIG;
        $dbConfig['driver'] = 'oci';

        $this->container->get('config')
            ->willReturn(
                [
                    'db' => $dbConfig,
                ]
            );

        $db = ($this->factory)($this->container->reveal());
        self::assertInstanceOf(Db::class, $db);
    }

    public function testWithSqlsrvDriverConfigKey()
    {
        $this->container->get('config')
            ->willReturn(
                [
                    'db' => self::SQLSRV_CONFIG,
                ]
            );

        $db = ($this->factory)($this->container->reveal());
        self::assertInstanceOf(Db::class, $db);
    }

    public function testWithPdoConfigKey()
    {
        $this->container->get('config')
            ->willReturn(
                [
                    'pdo' => self::PDO_CONFIG,
                ]
            );

        $db = ($this->factory)($this->container->reveal());
        self::assertInstanceOf(Db::class, $db);
    }
}

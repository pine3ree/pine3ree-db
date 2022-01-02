<?php

/**
 * @package     p3-db
 * @subpackage  p3-db-test
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest\Factory;

use P3\Db\Exception\InvalidArgumentException;
use P3\Db\Db;
use P3\Db\Factory\DbFactory;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Container\ContainerInterface;

// @codingStandardsIgnoreStart
if (trait_exists(ProphecyTrait::class)) {
    class DbFactoryTestBase extends TestCase
    {
       use ProphecyTrait;
    }
} else {
    class DbFactoryTestBase extends TestCase
    {
    }
}
// @codingStandardsIgnoreEnd

// @codingStandardsIgnoreLine
class DbFactoryTest extends DbFactoryTestBase
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
        $this->container = $this->container->reveal();

        $this->factory = new DbFactory();
    }

    public function tearDown(): void
    {
        $this->factory = null;
        $this->container = null;
    }

    public function testFactoryWithDsnProvidedInConfig()
    {
        $container = clone $this->container;
        $container->getProphecy()
            ->get('config')
            ->willReturn(
                [
                    'pdo' => [
                        'dsn' => 'mysql:host=localhost;port=3306;dbname=test',
                        'username' => 'testuser',
                        'password' => 'secret',
                    ]
                ]
            );

        $db = ($this->factory)($this->container);

        self::assertInstanceOf(Db::class, $db);
    }

    public function testFactoryWithFullDbConfig()
    {
        $container = clone $this->container;
        $container->getProphecy()
            ->get('config')
            ->willReturn([
                'db' => self::DB_CONFIG,
            ]);

        $db = ($this->factory)($this->container);
        self::assertInstanceOf(Db::class, $db);
    }

    public function testThatMissingConfigRaisesException()
    {
        $dbConfig = self::DB_CONFIG;
        unset($dbConfig['driver']);

        $container = clone $this->container;
        $container->getProphecy()
            ->get('config')
            ->willReturn(
                [
                ]
            );

        $this->expectException(InvalidArgumentException::class);
        $db = ($this->factory)($this->container);
    }

    public function testThatMissingDriverConfigKeyRaisesException()
    {
        $dbConfig = self::DB_CONFIG;
        unset($dbConfig['driver']);

        $container = clone $this->container;
        $container->getProphecy()
            ->get('config')
            ->willReturn(
                [
                    'db' => $dbConfig,
                ]
            );

        $this->expectException(InvalidArgumentException::class);
        $db = ($this->factory)($this->container);
    }

    public function testThatUnsupportedDriverConfigKeyRaisesException()
    {
        $dbConfig = self::DB_CONFIG;
        $dbConfig['driver'] = 'myfancydbdriver';

        $container = clone $this->container;
        $container->getProphecy()
            ->get('config')
            ->willReturn(
                [
                    'db' => $dbConfig,
                ]
            );

        $this->expectException(InvalidArgumentException::class);
        $db = ($this->factory)($this->container);
    }

    public function testThatMissingDatabaseConfigKeyRaisesException()
    {
        $dbConfig = self::DB_CONFIG;
        unset($dbConfig['database'], $dbConfig['dbname']);

        $container = clone $this->container;
        $container->getProphecy()
            ->get('config')
            ->willReturn(
                [
                    'db' => $dbConfig,
                ]
            );

        $this->expectException(InvalidArgumentException::class);
        $db = ($this->factory)($this->container);
    }

    public function testWithMysqlDriverAndUnixSocketConfigKey()
    {
        $dbConfig = self::DB_CONFIG;
        $dbConfig['unix_socket'] = '/var/run/mysql.sock';

        $container = clone $this->container;
        $container->getProphecy()
            ->get('config')
            ->willReturn(
                [
                    'db' => $dbConfig,
                ]
            );

        $db = ($this->factory)($this->container);
        self::assertInstanceOf(Db::class, $db);
    }

    public function testWithPgsqlDriverConfigKey()
    {
        $dbConfig = self::DB_CONFIG;
        $dbConfig['driver'] = 'pgsql';

        $container = clone $this->container;
        $container->getProphecy()
            ->get('config')
            ->willReturn(
                [
                    'db' => $dbConfig,
                ]
            );

        $db = ($this->factory)($this->container);
        self::assertInstanceOf(Db::class, $db);
    }

    public function testWithSqliteDriverConfigKey()
    {
        $dbConfig = self::DB_CONFIG;
        $dbConfig['driver'] = 'sqlite';
        $dbConfig['dbname'] = ':memory:';

        $container = clone $this->container;
        $container->getProphecy()
            ->get('config')
            ->willReturn(
                [
                    'db' => $dbConfig,
                ]
            );

        $db = ($this->factory)($this->container);
        self::assertInstanceOf(Db::class, $db);
    }

    public function testWithOciDriverConfigKey()
    {
        $dbConfig = self::DB_CONFIG;
        $dbConfig['driver'] = 'oci';

        $container = clone $this->container;
        $container->getProphecy()
            ->get('config')
            ->willReturn(
                [
                    'db' => $dbConfig,
                ]
            );

        $db = ($this->factory)($this->container);
        self::assertInstanceOf(Db::class, $db);
    }

    public function testWithSqlsrvDriverConfigKey()
    {
        $container = clone $this->container;
        $container->getProphecy()
            ->get('config')
            ->willReturn(
                [
                    'db' => self::SQLSRV_CONFIG,
                ]
            );

        $db = ($this->factory)($this->container);
        self::assertInstanceOf(Db::class, $db);
    }

    public function testWithPdoConfigKey()
    {
        $container = clone $this->container;
        $container->getProphecy()
            ->get('config')
            ->willReturn(
                [
                    'pdo' => self::PDO_CONFIG,
                ]
            );

        $db = ($this->factory)($container);
        self::assertInstanceOf(Db::class, $db);
    }

    /**
     *
     * @dataProvider provideConfig
     */
    public function testCreateStaticMethod(array $config)
    {
        self::assertInstanceOf(Db::class, $db = DbFactory::create($config));
    }

    public function provideConfig(): array
    {
        return [
            [self::PDO_CONFIG],
            [self::DB_CONFIG],
            [array_merge(self::DB_CONFIG, ['driver' => 'sqlite', 'dbname' => ':memory:'])],
            [array_merge(self::DB_CONFIG, ['driver' => 'pgsql'])],
            [array_merge(self::DB_CONFIG, ['driver' => 'oci'])],
            [self::SQLSRV_CONFIG],
        ];
    }
}

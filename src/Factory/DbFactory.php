<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Factory;

use InvalidArgumentException;
use P3\Db\Db;
use Psr\Container\ContainerInterface;
use RuntimeException;

use function in_array;

/**
 * Class DbFactory
 */
class DbFactory
{
    private $params = [];

    public function __invoke(ContainerInterface $container): Db
    {
        $config = $container->has('config') ? $container->get('config') : null;

        if (empty($this->params)) {
            $this->marshallParams($config);
        }

        return new Db(
            $this->params['dsn'],
            $this->params['username'],
            $this->params['password'],
            $this->params['options']
        );
    }

    private function marshallParams(array $config = null)
    {
        $config = $config['pdo'] ?? $config['db'] ?? null;

        if (!isset($config)) {
            throw new InvalidArgumentException(
                "Missind `PDO` configuration!"
            );
        }

        $this->params['dsn'] = $config['dsn'] ?? $this->buildDSN($config);
        $this->params['username'] = $config['username'] ?? $config['user'] ?? null;
        $this->params['password'] = $config['password'] ?? $config['passwd'] ?? $config['pass'] ?? null;
        $this->params['options']  = $config['options'] ?? [];
    }

    private function buildDSN(array $config): string
    {
        $driver = $config['driver'] ?? null;

        if (empty($driver)) {
            throw new InvalidArgumentException(
                "Missing PDO `dsn` or `driver` name!"
            );
        }

        if (!in_array($driver, ['mysql', 'sqlite', 'pgsql', 'oci', 'sqlsrv'], true)) {
            throw new InvalidArgumentException(
                "Invalid PDO driver type `{$driver}`!"
            );
        }

        $dbname = $config['dbname'] ?? $config['database'] ?? null;
        if (empty($dbname)) {
            throw new InvalidArgumentException(
                "Missing PDO dbname!"
            );
        }

        switch ($driver) {
            case 'mysql':
                return $this->buildDsnForMySql($dbname, $config);
            case 'pgsql':
                return $this->buildDsnForPgSql($dbname, $config);
            case 'sqlite':
                return "sqlite:{$dbname}";
            case 'oci':
            case 'sqlsrv':
        }

        throw new RuntimeException(
            "Unable to build a DSN for driver {$driver}!"
        );
    }

    private function buildDsnForMySql(string $dbname, array $config): string
    {
        $dsn = "mysql:dbname={$dbname}";

        $unix_socket = $config['unix_socket'] ?? null;
        if (!empty($unix_socket)) {
            $dsn .= ";unix_socket={$unix_socket}";
        } else {
            $host = $config['host'] ?? $config['hostname'] ?? null;
            if (!empty($host)) {
                $dsn .= ";host={$host}";
            }
            $port = $config['port'] ?? null;
            if (is_int($port)) {
                $dsn .= ";port={$port}";
            }
        }

        $charset = $config['charset'] ?? null;
        if (!empty($charset)) {
            $dsn .= ";charset={$charset}";
        }

        return $dsn;
    }

    private function buildDsnForPgSql(string $dbname, array $config): string
    {
        $dsn = "pgsql:dbname={$dbname}";

        $host = $config['host'] ?? $config['hostname'] ?? null;
        if (!empty($host)) {
            $dsn .= ";host={$host}";
        }

        $port = $config['port'] ?? null;
        if (is_int($port)) {
            $dsn .= ";port={$port}";
        }

        return $dsn;
    }

    private function buildDsnForOci(string $dbname, array $config): string
    {
        $host = $config['host'] ?? $config['hostname'] ?? null;
        if (!empty($host)) {
            $dbprefix = "{$host}";
        }

        $port = $config['port'] ?? null;
        if (is_int($port)) {
            if (empty($host)) {
                $host = "localhost";
            }
            $dbprefix = "{$host}:{$port}";
        }

        if (!empty($dbprefix)) {
            $dbname = "//{$dbprefix}/{$dbname}";
        }

        return "oci:dbname={$dbname}";
    }
}

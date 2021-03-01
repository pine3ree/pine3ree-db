<?php

/**
 * @package p3-db
 * @author  pine3ree https://github.com/pine3ree
 */

namespace P3\Db\Factory;

use InvalidArgumentException;
use P3\Db\Db;
use Psr\Container\ContainerInterface;
use RuntimeException;

use function array_change_key_case;
use function filter_var;
use function implode;
use function is_int;

use const FILTER_VALIDATE_BOOLEAN;

/**
 * DbFactory builds a Db instance from configuration retrieved from the container
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

        if (empty($config)) {
            throw new InvalidArgumentException(
                "Missing `PDO` configuration!"
            );
        }

        $config = array_change_key_case($config, CASE_LOWER);

        $this->params['dsn']      = $config['dsn'] ?? $this->buildDSN($config);
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
        if (!Db::supportsDriver($driver)) {
            throw new InvalidArgumentException(
                "Unsupported or invalid PDO driver type `{$driver}`!"
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
                return $this->buildDSNforMySql($dbname, $config);
            case 'pgsql':
                return $this->buildDSNforPgSql($dbname, $config);
            case 'sqlite':
                return "sqlite:{$dbname}";
            case 'oci':
                return $this->buildDSNforOci($dbname, $config);
            case 'sqlsrv':
                return $this->buildDSNforSqlSrv($dbname, $config);
        }

        throw new RuntimeException(
            "Unable to build a DSN for driver `{$driver}`!"
        );
    }

    private function buildDSNforMySql(string $dbname, array $config): string
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

    private function buildDSNforPgSql(string $dbname, array $config): string
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

    private function buildDSNforOci(string $dbname, array $config): string
    {
        $host = $config['host'] ?? $config['hostname'] ?? null;
        if (!empty($host)) {
            $dbprefix = "{$host}";
        }

        $port = $config['port'] ?? null;
        if (is_int($port)) {
            $dbprefix = ($host ?: 'localhost') . ":{$port}";
        }

        if (!empty($dbprefix)) {
            $dbname = "//{$dbprefix}/{$dbname}";
        }

        return "oci:dbname={$dbname}";
    }

    private function buildDSNforSqlSrv(string $dbname, array $config): string
    {
        $dsn = [];

        $server = $config['server'] ?? $config['host'] ?? $config['hostname'] ?? null;
        if (!empty($server)) {
            $port = $config['port'] ?? null;
            if (is_int($port)) {
                $server .= ",{$port}";
            }
            $dsn[] = "Server={$server}";
        }

        $dsn[] = "Database={$dbname}";

        $app = $config['app'] ?? null;
        if (!empty($app)) {
            $dsn[] = "APP={$app}";
        }

        $encrypt = $config['encrypt'] ?? null;
        if (isset($encrypt)) {
            $encrypt = (int)$encrypt;
            $dsn[] = "Encrypt={$encrypt}";
        }

        $failover_partner = $config['failover_partner'] ?? null;
        if (!empty($failover_partner)) {
            $dsn[] = "Failover_Partner={$failover_partner}";
        }

        $login_timeout = $config['login_timeout'] ?? $config['logintimeout'] ?? null;
        if (is_int($login_timeout)) {
            $dsn[] = "LoginTimeout={$login_timeout}";
        }

        $mars = $config['multiple_active_result_sets']
            ?? $config['multipleactiveresultsets']
            ?? $config['mars']
            ?? null;
        if (!empty($mars)) {
            $dsn[] = "MultipleActiveResultSets={$mars}";
        }

        $quoted_id = $config['quoted_id'] ?? $config['quotedid'] ?? null;
        if (isset($quoted_id)) {
            $quoted_id = (int)filter_var($quoted_id, FILTER_VALIDATE_BOOLEAN);
            $dsn[] = "QuotedId={$quoted_id}";
        }

        $pooling = $config['connection_pooling'] ?? $config['connectionpooling'] ?? null;
        if (isset($pooling)) {
            $pooling = (int)filter_var($pooling, FILTER_VALIDATE_BOOLEAN);
            $dsn[] = "ConnectionPooling={$pooling}";
        }

        $trace_file = $config['trace_file'] ?? $config['tracefile'] ?? null;
        if (isset($trace_file)) {
            $dsn[] = "TraceFile={$trace_file}";
        }

        $trace_on = $config['trace_on'] ?? $config['traceon'] ?? null;
        if (isset($trace_on)) {
            $trace_on = (int)filter_var($trace_on, FILTER_VALIDATE_BOOLEAN);
            $dsn[] = "TraceOn={$trace_on}";
        }

        $transaction_isolation = $config['transaction_isolation'] ?? $config['transactionisolation'] ?? null;
        if (is_int($transaction_isolation)) {
            $dsn[] = "TransactionIsolation={$transaction_isolation}";
        }

        $trust_srv_cert = $config['trust_server_certificate'] ?? $config['trustservercertificate'] ?? null;
        if (isset($trust_srv_cert)) {
            $trust_srv_cert = (int)filter_var($trust_srv_cert, FILTER_VALIDATE_BOOLEAN);
            $dsn[] = "TrustServerCertificate={$trust_srv_cert}";
        }

        $wsid = $config['wsid'] ?? null;
        if (!empty($wsid)) {
            $dsn[] = "WSID={$wsid}";
        }

        return 'sqlsrv:' . implode(';', $dsn);
    }
}

<?php

/**
 * @package     package
 * @subpackage  package-subpackage
 * @author      pine3ree https://github.com/pine3ree
 */

namespace P3\DbTest;

use PHPUnit\Framework\TestCase;
use PDO;

/**
 * Class QueryTest
 */
class QueryTest extends TestCase
{

    private $pdo;

    public function setUp(): void
    {
        $this->pdo = new PDO(
            'mysql:host=localhost;dbname=p3im_mz_31;charset=utf8',
            'root',
            'pegasus',
            []
        );
    }


    /**
     * @dataProvider providesUpdatedAt
     */
    public function testNULLs($updated_at)
    {

        $stmt = $this->pdo->prepare("SELECT * FROM __user u WHERE u.updated_at = :updated_at LIMIT 5");
        $stmt = $this->pdo->prepare("SELECT * FROM __user u WHERE u.updated_at IN (:updated_at) LIMIT 5");
        $stmt = $this->pdo->prepare("SELECT * FROM __user u WHERE u.updated_at IS :updated_at LIMIT 5");
        $stmt->bindValue(':updated_at', $updated_at, is_null($updated_at) ? PDO::PARAM_NULL : PDO::PARAM_INT);
//        $stmt->bindValue(':number', 123, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        print_R($rows);

        $this->assertTrue(is_array($rows));
    }


    public function providesUpdatedAt(): array
    {
        return [
            [null],
            [1587857945],
        ];
    }

    public function tearDown()
    {
        $this->pdo = null;
    }
}

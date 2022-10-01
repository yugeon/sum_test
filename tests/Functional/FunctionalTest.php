<?php

namespace App\Tests\Functional;

use App\InProcessException;
use App\Storage\Redis;
use App\Storage\Mysql;
use PHPUnit\Framework\TestCase;
use App\SumController;

class FunctionalTest extends TestCase
{
    const IKEY_EXISTS = 'abc';
    const IKEY_NOT_EXISTS = 'zxc';
    const IKEY_IN_PROGRESS = 'asdf';

    protected ?SumController $testClass = null;
    protected static ?Redis $redisStorage = null;
    protected static ?Mysql $mysqlStorage = null;
    protected static mixed $mysqlConfig = [
        'dsn' => 'mysql:host=mariadb;port=3306;dbname=test',
        'username' =>  'test',
        'password' =>  'test',
    ];
    protected static mixed $redisConfig = [
        'host' => 'redis',
        'port' => 6379,
        'dbindex' => 0,
    ];

    public static function setUpBeforeClass(): void
    {
        self::setUpRedis();
        self::setUpMysql();
    }

    protected static function setUpMysql(): void
    {
        self::$mysqlStorage = new Mysql(self::$mysqlConfig);
        self::$mysqlStorage->initPdo();
        $conn = self::$mysqlStorage::$pdo;

        $conn->query("CREATE TABLE IF NOT EXISTS sumtable (
            id                   INT UNSIGNED NOT NULL  AUTO_INCREMENT  PRIMARY KEY,
	        sumvalue             INT UNSIGNED
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        $conn->query("TRUNCATE TABLE sumtable");
    }

    protected static function setUpRedis(): void
    {
        self::$redisStorage = new Redis(self::$redisConfig);

        self::$redisStorage->initRedis();
        self::$redisStorage::$redis->flushDB();
    }

    function setUp(): void
    {
        $this->testClass = new SumController;
        $this->testClass->setRedisStorage(self::$redisStorage);
        $this->testClass->setMysqlStorage(self::$mysqlStorage);
    }

    public static function tearDownAfterClass(): void
    {

    }

    public function testCallActionWithoutParamsOnEmptyBase(): void
    {
        $result = $this->testClass->sumAction();
        $this->assertEquals(0, $result);
    }

    public function testCallActionWithNewKey(): void
    {
        $result = $this->testClass->sumAction(self::IKEY_EXISTS, 5);
        $this->assertEquals(5, $result);
    }

    public function testCallActionWithSomeKey(): void
    {
        $result = $this->testClass->sumAction(self::IKEY_EXISTS, 15);

        $this->assertEquals(5, $result);
    }

    public function testCallActionWithEmptyNumber(): void
    {
        $this->assertEquals(5, $this->testClass->sumAction(self::IKEY_EXISTS));
        $this->assertEquals(5, $this->testClass->sumAction(self::IKEY_NOT_EXISTS));
    }

    public function testCallActionWithDiffertenKeys(): void
    {
        $this->assertEquals(15, $this->testClass->sumAction(self::IKEY_NOT_EXISTS . '1', 10));
        $this->assertEquals(22, $this->testClass->sumAction(self::IKEY_NOT_EXISTS . '2', 7));
        $this->assertEquals(31, $this->testClass->sumAction(self::IKEY_NOT_EXISTS . '3', 9));
    }

    public function testSimulateDbConnectionLost(): void
    {
        self::$mysqlStorage::$pdo = null;
        self::$mysqlStorage->setConfig([
            'dsn' => 'mysql:host=mariadb_broken;port=3306;dbname=test',
            'username' => '',
            'password' => ''
        ]);

        try {
            $this->testClass->sumAction(self::IKEY_NOT_EXISTS . '4', 10);
            $this->assertTrue(false);
        } catch (\PDOException $th) {
            $this->assertTrue(true);
        }

        try {
            $this->testClass->sumAction(self::IKEY_NOT_EXISTS . '4', 10);
            $this->assertTrue(false);
        } catch (InProcessException $th) {
            $this->assertTrue(true);
        }

        // restore connection
        self::$mysqlStorage->setConfig(self::$mysqlConfig);

        $this->expectException(InProcessException::class);
        $this->assertEquals(0, $this->testClass->sumAction(self::IKEY_NOT_EXISTS . '4', 10));
    }

    public function testSimulateRedisConnectionLost(): void
    {
        self::$redisStorage::$redis = null;
        self::$redisStorage->setConfig([
                'host' => 'redis_broken',
                'port' => 6379,
                'dbindex' => 0,
        ]);

        try {
            $this->testClass->sumAction(self::IKEY_NOT_EXISTS . '5', 11);
        } catch (\RedisException $th) {
            $this->assertTrue(true);
        }

        // restore redis connection
        self::$redisStorage->setConfig(self::$redisConfig);
        $this->assertEquals(42, $this->testClass->sumAction(self::IKEY_NOT_EXISTS . '5', 11));

    }

}
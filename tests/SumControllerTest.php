<?php

namespace App\Tests;

use App\InProcessException;
use PHPUnit\Framework\TestCase;
use App\SumController;
use App\SumControllerInterface;
use ReflectionClass;
use TypeError;

class ControllerTest extends TestCase
{
    const IKEY_EXISTS = 'abc';
    const IKEY_NOT_EXISTS = 'zxc';
    const IKEY_IN_PROGRESS = 'asdf';

    protected ?SumController $testClass = null;

    function setUp(): void
    {
        $this->testClass = new SumController();
        $this->setUpRedisMock();
        $this->setUpMysqlMock();
    }

    protected function setUpRedisMock(): void
    {
        /** @var \PHPUnit\Framework\MockObject\MockObject */
        $redisMock = $this->createMock('App\Storage\Redis');
        $redisMockReturnMap = [
            [self::IKEY_EXISTS, 45],
            [self::IKEY_NOT_EXISTS, false],
            [self::IKEY_IN_PROGRESS, $this->throwException(new InProcessException())],
        ];
        $redisMock->method('getOrMarkInProgress')->will($this->returnValueMap($redisMockReturnMap));

        /** @var \App\Storage\Redis */
        $redisObj = $redisMock;
        $this->testClass->setRedisStorage($redisObj);
    }

    protected function setUpMysqlMock(): void
    {
        /** @var \PHPUnit\Framework\MockObject\MockObject */
        $mysqlMock = $this->createMock('App\Storage\Mysql');
        $mysqlMock->method('getCurrentSum')->willReturn(140);
        $mysqlMock->method('addNumber')->willReturn(145);

        /** @var \App\Storage\Mysql*/
        $mysqlObj = $mysqlMock;
        $this->testClass->setMysqlStorage($mysqlObj);
    }

    public function testClassCanBeInstantiated(): void
    {
        $this->assertTrue(is_object($this->testClass));
    }

    public function testObjectIsOfCorrectType(): void
    {
        $this->assertTrue(get_class($this->testClass) == 'App\SumController');
    }

    public function testMustImplementControllerInterface(): void
    {
        $this->assertTrue($this->testClass instanceof SumControllerInterface);
    }

    public function testHaveActionMethod(): void
    {
        $this->assertTrue(method_exists($this->testClass, 'sumAction'));
    }

    public function testActionMethodCanAcceptTwoOptionalParams(): void
    {
        $testClassReflection = new ReflectionClass($this->testClass);
        $this->assertEquals(0, $testClassReflection->getMethod('sumAction')->getNumberOfRequiredParameters());
        $this->assertEquals(2, $testClassReflection->getMethod('sumAction')->getNumberOfParameters());
    }

    public function testActionMethodMustAccumulateSum(): void
    {
        $this->assertEquals(145, $this->testClass->sumAction(self::IKEY_NOT_EXISTS, 4));
    }

    public function testActionMethodMustReturnValueFromCache(): void
    {
        $this->assertEquals(45, $this->testClass->sumAction(self::IKEY_EXISTS, 4));
    }

    public function testActionMethodMustThrowExceptionIfInProgress(): void
    {
        $this->expectException(TypeError::class);
        $this->testClass->sumAction(self::IKEY_IN_PROGRESS, 4);
    }

    public function testCanReturnCurrentSumIfEmptyParams(): void
    {
        $this->assertEquals(140, $this->testClass->sumAction());
    }

    public function testInputNumberIsNatural(): void
    {
        $this->assertEquals(140, $this->testClass->sumAction(self::IKEY_NOT_EXISTS, -1));
        $this->assertEquals(45, $this->testClass->sumAction(self::IKEY_EXISTS, -1));
    }

    public function testDontMustDoubleSumIfKeyNotUnique(): void
    {
        $this->assertEquals(45, $this->testClass->sumAction(self::IKEY_EXISTS, 4));
        $this->assertEquals(45, $this->testClass->sumAction(self::IKEY_EXISTS, 4));
    }

}
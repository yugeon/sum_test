<?php

declare(strict_types=1);

namespace App;

use App\Storage\Redis;
use App\Storage\Mysql;

class SumController implements SumControllerInterface
{
    protected ?int $currentSum = null;
    protected ?Redis $redisStorage = null;
    protected ?Mysql $mysqlStorage = null;

    /**
     * @inheritDoc
     */
    public function sumAction(?string $key = null, ?int $number = null): int
    {
        $key ??= '';
        $number ??= 0;

        // check if key exists and return saved value
        if (!empty($key)) {
            $sum = $this->getOrMarkInProgress($key);

            if (false !== $sum) {
                // return cached sum
                return $sum;
            }
        }

        // empty key or new key
        if ($number > 0) {
            // do accumulate sum
            $sum = $this->addNumber($number);
        } else {
            // number not natural, simple get current sum from storage
            $sum =  $this->getSumFromStorage($key);
        }

        $this->cacheSum($key, $sum);
        return $sum;
    }

    /**
     * Get cached value of sum or mark key as in progress
     *
     * @param string $key
     * @return integer|false False if key not exists
     */
    protected function getOrMarkInProgress(string $key): int|false
    {
        $redisStorage = $this->getRedisStorage();
        return $redisStorage->getOrMarkInProgress($key);
    }

    /**
     * Get sum from persisten storage
     *
     * @param string $key
     * @return integer
     */
    protected function getSumFromStorage(string $key): int
    {
        if (!empty($this->currentSum)) {
            return $this->currentSum;
        }

        try {
            $mysqlStorage = $this->getMysqlStorage();
            $this->currentSum = $mysqlStorage->getCurrentSum();
            return $this->currentSum;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Do accumulate sum
     *
     * @param integer $number
     * @return integer
     */
    protected function addNumber(int $number): int
    {
        try {
            $mysqlStorage = $this->getMysqlStorage();
            $this->currentSum = $mysqlStorage->addNumber($number);
            return $this->currentSum;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    protected function cacheSum(string $key, int $sum): void
    {
        $redisStorage = $this->getRedisStorage();
        $redisStorage->saveByKey($key, $sum);
    }

    protected function getRedisStorage(): Redis
    {
        return $this->redisStorage ??= new Redis();
    }

    public function setRedisStorage(Redis $redisStorage): self
    {
        $this->redisStorage = $redisStorage;
        return $this;
    }

    protected function getMysqlStorage(): Mysql
    {
        return $this->mysqlStorage ??= new Mysql();
    }

    public function setMysqlStorage(Mysql $mysqlStorage): self
    {
        $this->mysqlStorage = $mysqlStorage;
        return $this;
    }

}

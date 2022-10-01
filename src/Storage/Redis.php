<?php

namespace App\Storage;

use App\InProcessException;

class Redis
{
    const PREFIX_KEY = 'ikey:';
    const IN_PROGRESS_VALUE = 'inprogress';
    const SAVE_INTERVAL = 24*60*60;

    public static ?\Redis $redis = null;

    protected mixed $config = [
        'host' => 'redis',
        'port' => 6379,
        'dbindex' => 0,
    ];

    function __construct(mixed $config = null)
    {
        if (!empty($config)) {
            $this->config = $config;
        }
    }

    public function setConfig(mixed $config): self
    {
        $this->config = $config;
        return $this;
    }

    public function initRedis(): void
    {
        $config = $this->config;

        if (!static::$redis) {
            try {
                static::$redis = new \Redis();
                $status = static::$redis->pconnect($config['host'], $config['port']);
                if (false === $status) {
                    throw new \Exception('Can\'t connect to redis server');
                }
                static::$redis->select($config['dbindex']);
            } catch (\Throwable $th) {
                error_log($th->getMessage());
                static::$redis = null;
                throw $th;
            }
        }
    }

    public function getByKey(string $key): int|false
    {
        $this->initRedis();
        $sum = static::$redis->get(self::PREFIX_KEY . $key);
        if ($sum) {
            return intval($sum);
        }

        return false;
    }

    public function saveByKey(string $key, int $sum = 0): void
    {
        $this->initRedis();
        static::$redis->setEx(self::PREFIX_KEY . $key, self::SAVE_INTERVAL, strval($sum));
    }

    /**
     * Get cached sum if key exists. Or mark key as in progress.
     *
     * @param string $key
     * @return integer|false False if $key not exists in storage
     * @throws InProcessException Throw InProgressException if $key already in processing
     */
    public function getOrMarkInProgress(string $key): int|false
    {
        $this->initRedis();

        // switch to atomic operation, PHP Redis dont support GET option in set command
        static::$redis->multi();

        // Mark $key as in progress if it not exists (NX)
        static::$redis->set(
            self::PREFIX_KEY . $key,
            self::IN_PROGRESS_VALUE,
            ['nx', 'ex' => self::SAVE_INTERVAL]
        );

        // Get value if exists
        static::$redis->get(self::PREFIX_KEY . $key);

        // execute atomic operation
        $result = static::$redis->exec();

        // key not exists
        if (true === $result[0]) {
            return false;
        }

        // key exists, but mark in progress
        if (self::IN_PROGRESS_VALUE === strval($result[1])) {
            throw new InProcessException("Operation already in progress, try later", 1);
        }

        // key exits and contains sum
        return intval($result[1]);
    }
}

<?php

namespace App\Storage;

class Mysql
{
    static public ?\PDO $pdo = null;
    protected mixed $config = [
        'dsn' => 'mysql:host=mariadb;port=3306;dbname=test',
        'username' =>  'test',
        'password' =>  'test',
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

    public function initPdo(): void
    {
        if (static::$pdo) {
            return;
        }

        $config = $this->config;
        $dsn = $config['dsn'];
        try {
            static::$pdo = new \PDO($dsn, $config['username'], $config['password'], []);
        } catch (\Throwable $th) {
            error_log($th->getMessage());
            static::$pdo = null;
            throw $th;
        }
    }

    /**
     * Return current sum
     *
     * @return integer
     */
    public function getCurrentSum(): int
    {
        $this->initPdo();

        try {
            $selectSql = "SELECT sumvalue FROM sumtable WHERE id=1";
            $stmt = static::$pdo->query($selectSql);
            if (false === $stmt) {
                throw new \Exception("Error Processing Request", 1);
            }

            $newSum = $stmt->fetch(\PDO::FETCH_COLUMN);
            return intval($newSum);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Accumulate number and return new sum
     *
     * @param integer $sum
     * @return integer
     */
    public function addNumber(int $sum): int
    {
        $this->initPdo();

        try {
            // no transaction, because atomic insert
            $insertOrUpdateSql = "INSERT INTO sumtable (id, sumvalue) VALUES (1, :sum)
                ON DUPLICATE KEY UPDATE sumvalue = sumvalue + :sum";
            $stmt = static::$pdo->prepare($insertOrUpdateSql);
            $stmt->execute(['sum' => $sum]);
            $affectedRows = $stmt->rowCount();

            if ($affectedRows > 0) {
                return $this->getCurrentSum();
            } else {
                throw new \Exception("Error Processing Request", 1);
            }

        } catch (\Throwable $th) {
            throw $th;
        }
    }

}

<?php

namespace App;

interface SumControllerInterface
{
    /**
     *
     * @param string|null $key Independency Key
     * @param integer|null $number Natural number
     * @return integer Sum of all natural number
     */
    public function sumAction(?string $key = null, ?int $number = null): int;
}

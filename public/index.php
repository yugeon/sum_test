<?php

use App\InProcessException;
use App\SumController;

define('APP_DIR', realpath(__DIR__ . '/../app'));
define('SRC_DIR', realpath(APP_DIR . '/src'));

function myExceptionHandler(\Throwable $e): void
{
    echo "Uncaught exception: {$e->getMessage()} {$e->getFile()} {$e->getLine()}" . PHP_EOL;

}

set_exception_handler('myExceptionHandler');

require_once '../vendor/autoload.php';

list($action, $params) = explode('?', $_SERVER['REQUEST_URI']);
if ('/test-sum-action' === $action) {
    $sumController = new SumController;
    try {
        $sum = $sumController->sumAction($_GET['ikey'] ?? null, $_GET['number'] ?? null);
        var_dump($sum);
    } catch (InProcessException $e) {
        echo 'Operation in progress, try later...';
    }


}

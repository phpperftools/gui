<?php
require dirname(__DIR__) . '/src/bootstrap.php';


// slim uses some functions that are raising deprecated error, we need to silence it for now
if (PHP_VERSION_ID >= 70400) {
    error_reporting(error_reporting() & ~E_DEPRECATED);
}

// some of the file operations could take a bit longer to complete
set_time_limit(60);

$di = new \PhpPerfTools\ServiceContainer();

$app = $di['app'];
error_reporting(E_ALL);
ini_set('display_errors', 1);
require PHPPERFTOOLS_ROOT_DIR . '/src/routes.php';

$app->run();

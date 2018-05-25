<?php

use PHPProxy\PHPProxy;

require __DIR__."/../autoload.php";
require __DIR__."/../config.php";

$app = new PHPProxy(PROXY_PASS, PROXY_HOST, PROXY_PORT, PROXY_TIMEOUT);

$app->captureRequest();

$app->run();

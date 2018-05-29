<?php

use PHPProxy\PHPProxy;

require __DIR__."/../autoload.php";
require __DIR__."/../config.php";

$app = new PHPProxy(
	PROXY_TARGET,
	PROXY_HOST,
	PROXY_PORT,
	PROXY_PATH
);

$app->captureRequest();

$app->run();

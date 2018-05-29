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

$app->bufferOnComplete = true;

$app->afterCaptureRequest(function (&$requestHeaders, &$responseBody) {
	// $requestHeaders = explode("\n", $requestHeaders);
	// var_dump($requestHeaders);die;
});

$app->beforeSendResponse(function (&$responseHeaders, &$responseBody, $first = true) {
	if ($first) {
		
	} else {
		$rr = @gzdecode($responseBody);
		$r1 = ["https://static.nhentai.net"];
		$r2 = ["https://n1.teainside.me"];
		if ($rr !== false) {
			$responseBody = gzencode(str_replace($r1, $r2, $rr));
		} else {
			$responseBody = str_replace($r1, $r2, $rr);
		}
	}
});

$app->run();

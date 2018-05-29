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

});

$app->beforeSendResponse(function (&$responseHeaders, &$responseBody, $first = true) {
	if ($first) {
		foreach ($responseHeaders as $key => $value) {
			if (preg_match("/content-security-policy|content-encoding/i", $value)) {
				unset($responseHeaders[$key]);
			}
		}
		$responseHeaders[] = "Content-Encoding: gzip";
	} else {
		$rr = @gzdecode($responseBody);
		if ($rr !== false) {
			$responseBody = gzencode(str_replace(["https", "m.facebook.com"], ["http", $_SERVER["HTTP_HOST"]], $rr));
		} else {
			$responseBody = str_replace("m.facebook.com", $_SERVER["HTTP_HOST"], $rr);
		}
	}
});

$app->run();

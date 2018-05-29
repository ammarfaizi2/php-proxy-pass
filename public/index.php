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
$app->useCurl = true;

$app->afterCaptureRequest(function (&$requestHeaders, &$responseBody) {
	if (! is_array($requestHeaders)) {
		$requestHeaders = explode("\n", $requestHeaders);	
	}
	foreach ($requestHeaders as $key => $value) {
		if (preg_match("/cf-visitor|cf-ray|cf-ipcountry|cf-connecting-ip/i", $value)) {
			unset($requestHeaders[$key]);
		}
	}
	// $requestHeaders = implode("\n", $requestHeaders);
	// var_dump($requestHeaders);die;
});

$app->beforeSendResponse(function (&$responseHeaders, &$responseBody, $first = true) {
	if ($first) {
		foreach ($responseHeaders as $key => &$value) {
			$value = str_replace(
					["nhentai.net"]
					,["teainside.me"]
					,$value
			);
		}
		
		foreach ($responseHeaders as $key => $value) {
			if (preg_match("/cf-visitor|cf-ray|cf-ipcountry|cf-connecting-ip|cloudflare/i", $value)) {
				unset($responseHeaders[$key]);
			}
		}
		$responseHeaders[] = "Content-Encoding: gzip";
	} else {
		$rr = @gzdecode($responseBody);
		$r1 = ["https://static.nhentai.net", "https://t.nhentai.net"];
		$r2 = ["https://n2.teainside.me", "https://n3.teainside.me"];
		if ($rr !== false) {
			$responseBody = gzencode(str_replace($r1, $r2, $rr));
		} else {
			$responseBody = str_replace($r1, $r2, $rr);
		}
	}
});

$app->run();

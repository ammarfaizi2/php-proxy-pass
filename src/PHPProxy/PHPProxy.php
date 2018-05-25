<?php

namespace PHPProxy;

/**
 * @author Ammar Faizi <ammarfaizi2@gmail.com> https://www.facebook.com/ammarfaizi2
 * @license MIT
 * @package PHPProxy
 */
class PHPProxy
{
	private $proxyPass;

	private $proxyHost;

	private $proxyPort;

	private $clientRequest;

	public function __construct($proxyPass, $proxyHost, $proxyPort = 80, $proxyTimeout = 60)
	{
		$this->proxyPass = $proxyPass;
		$this->proxyHost = $proxyHost;
		$this->proxyPort = $proxyPort;
		$this->proxyTimeout = $proxyTimeout;
		if (! function_exists('getallheaders')) {
		    function getallheaders() {
		        $headers = [];
		        foreach ($_SERVER as $name => $value) {
		            if (substr($name, 0, 5) == 'HTTP_') {
		                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
		            }
		        }
		        return $headers;
		    }
		}
	}

	public function captureRequest()
	{		
		$this->clientRequest = [
			"uri" => isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : "/",
			"request_method" => isset($_SERVER["REQUEST_METHOD"]) ? $_SERVER["REQUEST_METHOD"] : "GET",
			"headers" => [], //getallheaders()
		];

		$this->clientRequest["headers"]["Host"] = $this->proxyHost;
		$this->clientRequest["headers"]["Connection"] = "closed";
	}

	public function run()
	{
		$clientHeaders = [];
		foreach ($this->clientRequest["headers"] as $key => $header) {
			$clientHeaders[] = "$key: $header";
		}
		$ch = curl_init($this->proxyPass.$this->proxyPort.$this->clientRequest["uri"]);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $clientHeaders,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_HEADER => true,
			CURLOPT_FOLLOWLOCATION => false
		]);
		$out = curl_exec($ch);
		$info = curl_getinfo($ch);
		$headers = substr($out, 0, $info["header_size"]);
		$out = substr($out, $info["header_size"]);
		$err = curl_error($ch);
		$ern = curl_errno($ch);
		foreach (explode("\n", $headers) as $header) {
			// $header = trim($header);
			// $header and header($header);
		}
		header("content-encoding: gzip");
		print $out;
	}
}



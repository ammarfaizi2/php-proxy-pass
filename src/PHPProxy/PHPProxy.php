<?php

namespace PHPProxy;

use Exception;

/**
 * @author Ammar Faizi <ammarfaizi2@gmail.com> https://www.facebook.com/ammarfaizi2
 * @license MIT
 * @package PHPProxy
 */
class PHPProxy
{
	/**
	 * @var resources
	 */
	private $fp;

	/**
	 * @var string
	 */
	private $target;

	/**
	 * @var string
	 */
	private $host;

	/**
	 * @var string
	 */
	private $url;

	/**
	 * @var string
	 */
	private $uri;

	/**
	 * @var int
	 */
	private $port;

	/**
	 * @var string
	 */
	private $protocol;

	/**
	 * @var array
	 */
	private $responseHeaders = [];

	/**
	 * @var array
	 */
	private $requestHeaders = [];

	/**
	 * @var string
	 */
	private $requestBody;

	/**
	 * @var int
	 */
	private $xpos = 0;

	/**
	 * @var array
	 */
	private $xar = [];

	/**
	 * @var string
	 */
	public $crlf = "\r\n";

	/**
	 * @var int
	 */
	public $timeout = 300;

	/**
	 * @var string
	 */
	private $error;

	/**
	 * @var int
	 */
	private $errno = 0;

	/**
	 * @var bool
	 */
	public $useCurl = false;

	/**
	 * @var bool
	 */
	public $bufferOnComplete = false;

	/**
	 * @var callable
	 */
	private $afterCaptureRequest;

	/**
	 * @var callable
	 */
	private $beforeSendResponse;

	/**
	 * @param string $target
	 * @param string $host
	 * @param int	 $port
	 * @param string $addPath
	 * @return void
	 *
	 * Constructor.
	 */
	public function __construct($target, $host = null, $port = null, $addPath = null)
	{
		$this->target 	= $target;
		$this->protocol = $this->scan("protocol");
		$this->host   	= is_null($host) ? $this->scan("host") : $host;
		$this->port   	= is_null($port) ? $this->scan("port") : $port;
		$this->path   	= is_null($addPath) ? $_SERVER["REQUEST_URI"] : $addPath.$_SERVER["REQUEST_URI"];
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

	/**
	 * @param callable $action
	 * @return void
	 * 
	 */
	public function afterCaptureRequest(callable $action)
	{
		$this->afterCaptureRequest = $action;
	}

	/**
	 * @param callable $action
	 * @return void
	 * 
	 */
	public function beforeSendResponse(callable $action)
	{
		$this->beforeSendResponse = $action;
	}


	/**
	 * @return void
	 *
	 * Prepare server http client.
	 */
	private function prepareSocks()
	{
		$this->requestHeaders 	= $this->buildRequestHeaders();
		$this->requestBody		= file_get_contents("php://input");
		$call = $this->afterCaptureRequest;
		$call($this->requestHeaders, $this->requestBody);
		$this->fp = fsockopen(
			($this->protocol=== "https" ? "ssl://" : "").$this->host,
			$this->port,
			$this->errno,
			$this->error,
			$this->timeout
		);
		if (! $this->fp) {
			header("Content-type:text/plain");
			echo "Error: {$this->error} ({$this->errno})";
			return false;
		}
		fwrite($this->fp, $this->requestHeaders);
		fwrite($this->fp, $this->requestBody);
		return true;
	}

	/**
	 * @return string
	 *
	 * Build http request header.
	 */
	private function buildRequestHeaders($type = "fsock")
	{
		$header = 
			$_SERVER["REQUEST_METHOD"]." ".$this->path." HTTP/1.0".$this->crlf
            ."Host: ".$this->host.$this->crlf;
        foreach (getallheaders() as $key => $value) {
        	$key === "Host" or $header .= "{$key}: ".$value.$this->crlf;
        }
        $header .= $this->crlf;
        return $header;
	}

	public function run()
	{
		$call = $this->beforeSendResponse;
		if ($this->useCurl) {
			$call2 = $this->afterCaptureRequest;
			$out = "";
			$ch = curl_init($this->protocol."://".$this->host.":".$this->port.$this->path);
			$header = array_map(function ($a) {
					return trim($a);
			}, explode($this->crlf, $this->buildRequestHeaders()));
			$call2($header, $out);
			$opt = [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER => true,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_HTTPHEADER => $header
			];
			if ($_SERVER["REQUEST_METHOD"] !== "GET" and ($in = file_get_contents("php://input"))!=="") {
				$opt[CURLOPT_POSTFIELDS] = $in;
			}
			curl_setopt_array($ch, $opt);
			$out = curl_exec($ch);
			curl_close($ch);
			$firstResponse = explode($this->crlf.$this->crlf, $out, 2);
			$headers = explode("\n", $firstResponse[0]);
			$call($headers, $firstResponse[1]);
			foreach ($headers as $header) {
				$header = trim($header);
				if (! empty($header)) {
					$this->responseHeaders[] = $header;
					header($header, false);
				}
			}
			$call($headers, $firstResponse[1], false);
			echo $firstResponse[1];
			flush();
			return;
		}

		if ($this->prepareSocks()) {
			if (is_resource($this->fp) && $this->fp && !feof($this->fp)) {
				$firstResponse = fread($this->fp, 2048);
				$firstResponse = explode($this->crlf.$this->crlf, $firstResponse, 2);
				$headers = explode("\n", $firstResponse[0]);
				$call($headers, $firstResponse[1]);
				foreach ($headers as $header) {
					$header = trim($header);
					if (! empty($header)) {
						$this->responseHeaders[] = $header;
						header($header, false);
					}
				}
				if ($this->bufferOnComplete) {
					$responseBody = $firstResponse[1];
					while(is_resource($this->fp) && $this->fp && !feof($this->fp)) {
						$responseBody .= fread($this->fp, 4096);
					}
					$call($headers, $responseBody, false);
					echo $responseBody;
				} else {
					echo $firstResponse[1];
					flush();
					while(is_resource($this->fp) && $this->fp && !feof($this->fp)) {
						$out = fread($this->fp, 1024);
						$call($headers, $out);
						echo $out;
						flush();
					}
				}
			}
	    }
	    is_resource($this->fp) and fclose($this->fp);
	}

	/**
	 * @param string $type
	 * @return mixed
	 *
	 * Scan action.
	 */
	private function scan($type)
	{
		switch ($type) {
			case "host":
				$req = substr($this->target, $this->xpos+3);
				$pos = strpos($req, '/');
				if($pos === false) {
					$pos = strlen($req);
				}
				return substr($req, 0, $pos);
				break;

			case "protocol":
        		return strtolower(
	        		substr(
        				$this->target,
        				0,
        				$this->xpos = strpos($this->target, '://')
        			)
        		);
				break;

			case "port":
				if(strpos($this->host, ':') !== false) {
		            list($this->host, $this->port) = explode(':', $host);
		        } else {
		            $this->port = ($this->protocol == 'https') ? 443 : 80;
		        }
		        return $this->port;
				break;

			default:
				throw new Exception("Invalid scan type");
				break;
		}
	}
}

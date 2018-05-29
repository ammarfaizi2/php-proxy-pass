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
		$this->fp = fsockopen(
			($this->protocol==="https"?"ssl://":"").$this->host, $this->port
		);
		$this->requestHeaders 	= $this->buildRequestHeaders();
		$this->requestBody		= file_get_contents("php://input");
		$call = $this->afterCaptureRequest;
		$call($this->requestHeaders, $this->requestBody);
		fwrite($this->fp, $this->requestHeaders);
		fwrite($this->fp, $this->requestBody);
	}

	/**
	 * @return string
	 *
	 * Build http request header.
	 */
	private function buildRequestHeaders()
	{
		$header = 
			$_SERVER["REQUEST_METHOD"]." ".$this->path." HTTP/1.0".$this->crlf
            ."Host: ".$this->host.$this->crlf;
        foreach (getallheaders() as $key => $value) {
        	$header .= "{$key}: ".$value.$this->crlf;
        }
        $header .= $this->crlf;
        return $header;
	}

	public function run()
	{
		$this->prepareSocks();
		if (is_resource($this->fp) && $this->fp && !feof($this->fp)) {
			$firstResponse = fread($this->fp, 2048);
			$firstResponse = explode($this->crlf.$this->crlf, $firstResponse, 2);
			$headers = explode("\n", $firstResponse[0]);
			$call = $this->beforeSendResponse;
			$call($headers, $firstResponse[1]);
			foreach ($headers as $header) {
				$header = trim($header);
				if (! empty($header)) {
					$this->responseHeaders[] = $header;
					header($header, false);
				}
			}
			flush();
			echo $firstResponse[1];
			flush();
			while(is_resource($this->fp) && $this->fp && !feof($this->fp)) {
				$out = fread($this->fp, 1024);
				call($headers, $out);
				echo $out;
				flush();
			}
		}
        fclose($this->fp);
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



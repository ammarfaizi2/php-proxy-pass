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
		$this->crlf 	= "\r\n";
		$this->target 	= $target;
		$this->protocol = $this->scan("protocol");
		$this->host   	= is_null($host) ? $this->scan("host") : $host;
		$this->port   	= is_null($port) ? $this->scan("port") : $port;
		$this->path   	= is_null($addPath) ? $_SERVER["REQUEST_URI"] : $addPath.$_SERVER["REQUEST_URI"];
	}

	public function captureRequest()
	{
	}

	private function prepareSocks()
	{
		$this->fp = fsockopen(
			($this->protocol==="https"?"ssl://":"").$this->host, $this->port
		);
		fwrite($this->fp, $this->buildRequestHeaders());
		fwrite($this->fp, file_get_contents("php://input"));
	}

	private function buildRequestHeaders()
	{
		$header = 
			$_SERVER["REQUEST_METHOD"]." ".$this->path." HTTP/1.0".$this->crlf
            ."Host: ".$this->host.$this->crlf
            .$this->crlf;
        return $header;
	}

	public function run()
	{
		$this->prepareSocks();
		if (is_resource($this->fp) && $this->fp && !feof($this->fp)) {
			$firstResponse = fread($this->fp, 2048);
			$firstResponse = explode($this->crlf.$this->crlf, $firstResponse, 2);
			$this->sendResponseHeaders($firstResponse[0]);
			echo $firstResponse[1];
			flush();
			while(is_resource($this->fp) && $this->fp && !feof($this->fp)) {
            	echo fread($this->fp, 1024);
            	flush();
			}
		}
        fclose($this->fp);
	}

	private function sendResponseHeaders($headers)
	{
		$headers = explode("\n", $headers);
		foreach ($headers as $header) {
			$header = trim($header);
			if (! empty($header)) {
				$this->responseHeaders[] = $header;
				if (preg_match("/set-cookie/i", $header)) {
					$header = preg_replace("/{$this->host}/i", $_SERVER["HTTP_HOST"], $header);
				}
				header($header);
			}
		}
		flush();
	}

	/**
	 * @param string $type
	 * @return mixed
	 *
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



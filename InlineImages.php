<?php
class InlineImages {
	/** IE cannot display inline data larger than 32KiB
		also processing large images would take too much time */
	const SIZE_LIMIT = 0; //#6536: disable inline images by setting 0kB for allowed size

	/** Regular expression to find URL definitions in CSS file */
	const REGEXP = '/(background(-image)?|list-style(-image)?|cursor|border-image):\ ?(\#[0-9a-f]{3,6}\ )?url\(([\'\"]?([^\'\"\)]+)[\'\"]?)\)/im';
	const REGIND = 6; //index of matching parenthesis where URL can be found

	/** Quotation character for CSS url() function
		- either ' or " (just in case you want to change it ;) */
	const QUOTE = '\'';
	const RESPONSIVE_PATH = '/css/responsive/';

	static protected $hash = null;

	private $path;
	private $responsive = null;

	/**
	 * Creates new instance of CSS image replacer
	 *
	 * @param  [String] path where to look for images - URLs in CSS must be relative to this path!
	 */
	public function __construct($path) {
		$this->path = $path;
	}

	/**
	 * Replace images in CSS file with inline data
	 *
	 * @param  [String] CSS file content
	 * @return [String] replaced CSS
	 */
	public function process($data) {
		if (false !== preg_match_all(self::REGEXP, $data, $matches)) {
			$images = array();
			foreach ($matches[self::REGIND] as $index => $url) {
				$key = $matches[self::REGIND - 1][$index]; //URL including quotes (that must be removed from file as well)
				if (!array_key_exists($key, $images)) { //images may be used many times but we need to load the only once
					$image = $this->getImage($url);
					if ($image) {
							$images[$key] = self::QUOTE . $image . self::QUOTE;
					}
				}
			}
			if (count($images)) {
				$data = strtr($data, $images);
				//Note: Minify does not call this when reading files from cache -> Minify must send these header as well!
				header('Vary: Accept-Encoding, DPR', false);
				header('Key: DPR;partition=1.0:1.5:2.0:3.0');
			}
		}
		return $data;
	}

	public function getImage($url, $allowPath = true) {
		$filename = realpath($this->path . $url);
		if (false !== $filename) {
			if (self::SIZE_LIMIT > filesize($filename)) {
				//get Base64-encoded content of the file
				$file = file_get_contents($filename);
				$base64 = base64_encode($file);
				//get Type of the file
				if (class_exists('finfo')) { //optional, requires PHP extension FileInfo (preferred)
					$finfo = new finfo(FILEINFO_MIME);
					$mime = $finfo->file($filename);
					$mime = explode(';', $mime);
					$mime = $mime[0]; //returns also charset
				}
				elseif (function_exists('mime_content_type')) { //optional, requires PHP extension MimeType (deprecated)
					$mime = mime_content_type($filename);
				} else { //old way of detecting MIME from file extension
					preg_match('/\.([^\.]+)$/', $filename, $mime);
					$mime = 'image/' . $mime[1];
				}
				//save the content
				if (self::SIZE_LIMIT > count($base64)) { //base64 increses file size by ~20% - check the limit again
					return 'data:' . $mime . ';base64,' . $base64;
				}
			}
			return ($allowPath ? $this->getResponsive($url) . '?' . self::getVersion($filename) : false);
		}
		return false;
	}

	function getClosest($search, $arr) {
		$closest = null;
		//$arr = array_reverse($arr,true); // - get better quality image first

		foreach($arr as $key => $item) {
		if($closest == null || abs($search - $closest) > abs($key - $search)) {
			$closest = $key;
		}
		}
		return $closest;
	}

	public function getResponsive($filename) {
		if (array_key_exists('HTTP_DPR', $_SERVER)) { //Client Hint header
			$dpr = '' . $_SERVER['HTTP_DPR'] * 10;
		}
		elseif (array_key_exists('dpr', $_GET)) {
			$dpr = '' . $_GET['dpr'];
		}

		if (!is_string($filename) || !isset($dpr)) {
			return $filename;
		}

		if(is_null($this->responsive)) {
			$this->responsive = $this->loadResponsiveDir();
		}
		$retFilename =  preg_replace("~(\\\\|\\/)~", "_", $filename);

		if(!array_key_exists($retFilename, $this->responsive)) {
			return $filename;
		}
		$avaibleV = $this->responsive[$retFilename];
		if(array_key_exists($dpr, $avaibleV)) {
			return self::RESPONSIVE_PATH.$avaibleV[$dpr];
		}

		$closest = $this->getClosest($dpr,$avaibleV);

		return self::RESPONSIVE_PATH.$avaibleV[$closest];
	}

	public static function getVersion($filename = NULL) {
		if (is_null($filename) || !file_exists($filename)) {
			if (is_null(self::$hash)) {
				@$version = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/public/version.txt');
				if (false === $version) { //if file_get_contents() fails, it returns false
					$version = time();
					//CSS and JS files are loaded with versioning param
					//so if this param is found, use it as hash and do not calculate new one
					foreach ($_GET as $param => $value) {
						if (is_numeric($param)) {
							self::$hash = $param;
							return $param;
						}
					}
				}
				self::$hash = hexdec(crc32($version)); //Minify needs number of version - create CRC32 and convert its HEXA representation into decimal number (prevents hackers to know server version)
			}
			return self::$hash;
		}
		else {
			return filemtime($filename); //modification time is automatically cached - repeated calling for same image does not degrade performance
		}

	}

	private function loadResponsiveDir() {
		$files = scandir($this->path.self::RESPONSIVE_PATH);
		$results = array();
		foreach($files as $key => $file) {
			if(($file[0] == "." )) {
				continue;
			}


			if (!preg_match("~^(.+)\.x([0-9]{2})(.+)$~",$file, $matches)) {
				continue;
			}
			$fName = $matches[1].$matches[3];
			if(!array_key_exists($fName,$results)) { $results[$fName] = array(); }

			$results[$fName][$matches[2]] = $file;


		}
		return $results;

	}

}

<?php

namespace App;

/**
 * Utils class.
 *
 * @copyright YetiForce Sp. z o.o
 * @license   YetiForce Public License 3.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author    Mariusz Krzaczkowski <m.krzaczkowski@yetiforce.com>
 */
class Utils
{
	/**
	 * Function to capture the initial letters of words.
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	public static function getInitials(string $name): string
	{
		preg_match_all('#(?<=\s|\b)\pL|[()]#u', $name, $initial);
		return isset($initial[0]) ? implode('', $initial[0]) : '';
	}

	/**
	 * Outputs or returns a parsable string representation of a variable.
	 *
	 * @see http://php.net/manual/en/function.var-export.php
	 *
	 * @param mixed $variable
	 *
	 * @return mixed the variable representation when the <i>return</i>
	 */
	public static function varExport($variable)
	{
		if (\is_array($variable)) {
			$toImplode = [];
			if (static::isAssoc($variable)) {
				foreach ($variable as $key => $value) {
					$toImplode[] = var_export($key, true) . '=>' . static::varExport($value);
				}
			} else {
				foreach ($variable as $value) {
					$toImplode[] = static::varExport($value);
				}
			}

			return '[' . implode(',', $toImplode) . ']';
		}
		return var_export($variable, true);
	}

	/**
	 * Check if array is associative.
	 *
	 * @param array $arr
	 *
	 * @return bool
	 */
	public static function isAssoc(array $arr)
	{
		if (empty($arr)) {
			return false;
		}
		return array_keys($arr) !== range(0, \count($arr) - 1);
	}

	/**
	 * Flatten a multi-dimensional array into a single level.
	 *
	 * @param array $array
	 * @param int   $depth
	 *
	 * @return array
	 */
	public static function flatten($array, $depth = INF)
	{
		$result = [];
		foreach ($array as $item) {
			if (!\is_array($item)) {
				$result[] = $item;
			} else {
				$values = 1 === $depth ? array_values($item) : static::flatten($item, $depth - 1);
				foreach ($values as $value) {
					$result[] = $value;
				}
			}
		}
		return $result;
	}

	/**
	 * Convert string from encoding to encoding.
	 *
	 * @param string $value
	 * @param string $fromCharset
	 * @param string $toCharset
	 *
	 * @return string
	 */
	public static function convertCharacterEncoding($value, $fromCharset, $toCharset)
	{
		if (\function_exists('mb_convert_encoding') && \function_exists('mb_list_encodings') && \in_array($fromCharset, mb_list_encodings()) && \in_array($toCharset, mb_list_encodings())) {
			$value = mb_convert_encoding($value, $toCharset, $fromCharset);
		} else {
			$value = iconv($fromCharset, $toCharset, $value);
		}
		return $value;
	}

	/**
	 * Function to check is a html message.
	 *
	 * @param string $content
	 *
	 * @return bool
	 */
	public static function isHtml(string $content): bool
	{
		$content = trim($content);
		if ('<' === substr($content, 0, 1) && '>' === substr($content, -1)) {
			return true;
		}
		return $content != strip_tags($content);
	}

	/**
	 * Strip tags content.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public static function htmlToText(string $content): string
	{
		return trim(preg_replace('/[ \t\n]+/', ' ', strip_tags($content)));
	}

	/**
	 * Function to save php file with cleaning file cache.
	 *
	 * @param string       $pathDirectory
	 * @param array|string $content
	 * @param string       $comment
	 * @param int          $flag
	 * @param bool         $return
	 *
	 * @return bool $value
	 */
	public static function saveToFile(string $pathDirectory, $content, string $comment = '', int $flag = LOCK_EX, bool $return = false): bool
	{
		if (\is_array($content)) {
			$content = self::varExport($content);
		}
		if ($return) {
			$content = "return $content;";
		}
		if ($comment) {
			$content = "<?php \n/**  {$comment}  */\n{$content}\n";
		} else {
			$content = "<?php $content" . PHP_EOL;
		}
		if (false !== $value = file_put_contents($pathDirectory, $content, $flag)) {
			Cache::resetFileCache($pathDirectory);
		}
		return (bool) $value;
	}

	/**
	 * Replacement for the ucfirst function for proper Multibyte String operation.
	 * Delete function will exist as mb_ucfirst.
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	public static function mbUcfirst($string)
	{
		return mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1);
	}

	/**
	 * Sanitize special chars from given string.
	 *
	 * @param string $string
	 * @param string $delimiter
	 *
	 * @return string
	 */
	public static function sanitizeSpecialChars(string $string, string $delimiter = '_'): string
	{
		$string = mb_convert_encoding((string) $string, 'UTF-8', mb_list_encodings());
		$replace = [
			'ъ' => '-', 'Ь' => '-', 'Ъ' => '-', 'ь' => '-',
			'Ă' => 'A', 'Ą' => 'A', 'À' => 'A', 'Ã' => 'A', 'Á' => 'A', 'Æ' => 'A', 'Â' => 'A', 'Å' => 'A', 'Ä' => 'Ae',
			'Þ' => 'B', 'Ć' => 'C', 'ץ' => 'C', 'Ç' => 'C', 'È' => 'E', 'Ę' => 'E', 'É' => 'E', 'Ë' => 'E', 'Ê' => 'E',
			'Ğ' => 'G', 'İ' => 'I', 'Ï' => 'I', 'Î' => 'I', 'Í' => 'I', 'Ì' => 'I', 'Ł' => 'L', 'Ñ' => 'N', 'Ń' => 'N',
			'Ø' => 'O', 'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'Oe', 'Ş' => 'S', 'Ś' => 'S', 'Ș' => 'S',
			'Š' => 'S', 'Ț' => 'T', 'Ù' => 'U', 'Û' => 'U', 'Ú' => 'U', 'Ü' => 'Ue', 'Ý' => 'Y', 'Ź' => 'Z', 'Ž' => 'Z',
			'Ż' => 'Z', 'â' => 'a', 'ǎ' => 'a', 'ą' => 'a', 'á' => 'a', 'ă' => 'a', 'ã' => 'a', 'Ǎ' => 'a', 'а' => 'a',
			'А' => 'a', 'å' => 'a', 'à' => 'a', 'א' => 'a', 'Ǻ' => 'a', 'Ā' => 'a', 'ǻ' => 'a', 'ā' => 'a', 'ä' => 'ae',
			'æ' => 'ae', 'Ǽ' => 'ae', 'ǽ' => 'ae',	'б' => 'b', 'ב' => 'b', 'Б' => 'b', 'þ' => 'b',	'ĉ' => 'c', 'Ĉ' => 'c',
			'Ċ' => 'c', 'ć' => 'c', 'ç' => 'c', 'ц' => 'c', 'צ' => 'c', 'ċ' => 'c', 'Ц' => 'c', 'Č' => 'c', 'č' => 'c',
			'Ч' => 'ch', 'ч' => 'ch', 'ד' => 'd', 'ď' => 'd', 'Đ' => 'd', 'Ď' => 'd', 'đ' => 'd', 'д' => 'd', 'Д' => 'D',
			'ð' => 'd', 'є' => 'e', 'ע' => 'e', 'е' => 'e', 'Е' => 'e', 'Ə' => 'e', 'ę' => 'e', 'ĕ' => 'e', 'ē' => 'e',
			'Ē' => 'e', 'Ė' => 'e', 'ė' => 'e', 'ě' => 'e', 'Ě' => 'e', 'Є' => 'e', 'Ĕ' => 'e', 'ê' => 'e', 'ə' => 'e',
			'è' => 'e', 'ë' => 'e', 'é' => 'e', 'ф' => 'f', 'ƒ' => 'f', 'Ф' => 'f', 'ġ' => 'g', 'Ģ' => 'g', 'Ġ' => 'g',
			'Ĝ' => 'g', 'Г' => 'g', 'г' => 'g', 'ĝ' => 'g', 'ğ' => 'g', 'ג' => 'g', 'Ґ' => 'g', 'ґ' => 'g', 'ģ' => 'g',
			'ח' => 'h', 'ħ' => 'h', 'Х' => 'h', 'Ħ' => 'h', 'Ĥ' => 'h', 'ĥ' => 'h', 'х' => 'h', 'ה' => 'h', 'î' => 'i',
			'ï' => 'i', 'í' => 'i', 'ì' => 'i', 'į' => 'i', 'ĭ' => 'i', 'ı' => 'i', 'Ĭ' => 'i', 'И' => 'i', 'ĩ' => 'i',
			'ǐ' => 'i', 'Ĩ' => 'i', 'Ǐ' => 'i', 'и' => 'i', 'Į' => 'i', 'י' => 'i', 'Ї' => 'i', 'Ī' => 'i', 'І' => 'i',
			'ї' => 'i', 'і' => 'i', 'ī' => 'i', 'ĳ' => 'ij', 'Ĳ' => 'ij', 'й' => 'j', 'Й' => 'j', 'Ĵ' => 'j', 'ĵ' => 'j',
			'я' => 'ja', 'Я' => 'ja', 'Э' => 'je', 'э' => 'je', 'ё' => 'jo', 'Ё' => 'jo', 'ю' => 'ju', 'Ю' => 'ju',
			'ĸ' => 'k', 'כ' => 'k', 'Ķ' => 'k', 'К' => 'k', 'к' => 'k', 'ķ' => 'k', 'ך' => 'k', 'Ŀ' => 'l', 'ŀ' => 'l',
			'Л' => 'l', 'ł' => 'l', 'ļ' => 'l', 'ĺ' => 'l', 'Ĺ' => 'l', 'Ļ' => 'l', 'л' => 'l', 'Ľ' => 'l', 'ľ' => 'l',
			'ל' => 'l', 'מ' => 'm', 'М' => 'm', 'ם' => 'm', 'м' => 'm', 'ñ' => 'n', 'н' => 'n', 'Ņ' => 'n', 'ן' => 'n',
			'ŋ' => 'n', 'נ' => 'n', 'Н' => 'n', 'ń' => 'n', 'Ŋ' => 'n', 'ņ' => 'n', 'ŉ' => 'n', 'Ň' => 'n', 'ň' => 'n',
			'о' => 'o', 'О' => 'o', 'ő' => 'o', 'õ' => 'o', 'ô' => 'o', 'Ő' => 'o', 'ŏ' => 'o', 'Ŏ' => 'o', 'Ō' => 'o',
			'ō' => 'o', 'ø' => 'o', 'ǿ' => 'o', 'ǒ' => 'o', 'ò' => 'o', 'Ǿ' => 'o', 'Ǒ' => 'o', 'ơ' => 'o', 'ó' => 'o',
			'Ơ' => 'o', 'œ' => 'oe', 'Œ' => 'oe', 'ö' => 'oe', 'פ' => 'p', 'ף' => 'p', 'п' => 'p', 'П' => 'p', 'ק' => 'q',
			'ŕ' => 'r', 'ř' => 'r', 'Ř' => 'r', 'ŗ' => 'r', 'Ŗ' => 'r', 'ר' => 'r', 'Ŕ' => 'r', 'Р' => 'r', 'р' => 'r',
			'ș' => 's', 'с' => 's', 'Ŝ' => 's', 'š' => 's', 'ś' => 's', 'ס' => 's', 'ş' => 's', 'С' => 's', 'ŝ' => 's',
			'Щ' => 'sch', 'щ' => 'sch', 'ш' => 'sh', 'Ш' => 'sh', 'ß' => 'ss', 'т' => 't', 'ט' => 't', 'ŧ' => 't',
			'ת' => 't', 'ť' => 't', 'ţ' => 't', 'Ţ' => 't', 'Т' => 't', 'ț' => 't', 'Ŧ' => 't', 'Ť' => 't', '™' => 'tm',
			'ū' => 'u', 'у' => 'u', 'Ũ' => 'u', 'ũ' => 'u', 'Ư' => 'u', 'ư' => 'u', 'Ū' => 'u', 'Ǔ' => 'u', 'ų' => 'u',
			'Ų' => 'u', 'ŭ' => 'u', 'Ŭ' => 'u', 'Ů' => 'u', 'ů' => 'u', 'ű' => 'u', 'Ű' => 'u', 'Ǖ' => 'u', 'ǔ' => 'u',
			'Ǜ' => 'u', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'У' => 'u', 'ǚ' => 'u', 'ǜ' => 'u', 'Ǚ' => 'u', 'Ǘ' => 'u',
			'ǖ' => 'u', 'ǘ' => 'u', 'ü' => 'ue', 'в' => 'v', 'ו' => 'v', 'В' => 'v', 'ש' => 'w', 'ŵ' => 'w', 'Ŵ' => 'w',
			'ы' => 'y', 'ŷ' => 'y', 'ý' => 'y', 'ÿ' => 'y', 'Ÿ' => 'y', 'Ŷ' => 'y', 'Ы' => 'y', 'ž' => 'z', 'З' => 'z',
			'з' => 'z', 'ź' => 'z', 'ז' => 'z', 'ż' => 'z', 'ſ' => 'z', 'Ж' => 'zh', 'ж' => 'zh', 'Ð' => 'D', 'Θ' => '8',
			'©' => '(c)', 'Α' => 'A', 'Β' => 'B', 'Γ' => 'G', 'Δ' => 'D', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'H', 'Ι' => 'I',
			'Κ' => 'K', 'Λ' => 'L', 'Μ' => 'M', 'Ν' => 'N', 'Ξ' => '3', 'Ο' => 'O', 'Π' => 'P',	'Ρ' => 'R', 'Σ' => 'S',
			'Τ' => 'T', 'Υ' => 'Y', 'Φ' => 'F', 'Χ' => 'X', 'Ψ' => 'PS', 'Ω' => 'W', 'Ά' => 'A', 'Έ' => 'E', 'Ί' => 'I',
			'Ό' => 'O', 'Ύ' => 'Y', 'Ή' => 'H', 'Ώ' => 'W', 'Ϊ' => 'I', 'Ϋ' => 'Y', 'α' => 'a', 'β' => 'b', 'γ' => 'g',
			'δ' => 'd', 'ε' => 'e', 'ζ' => 'z', 'η' => 'h', 'θ' => '8', 'ι' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'm',
			'ν' => 'n', 'ξ' => '3', 'ο' => 'o', 'π' => 'p', 'ρ' => 'r', 'σ' => 's', 'τ' => 't', 'υ' => 'y', 'φ' => 'f',
			'χ' => 'x', 'ψ' => 'ps', 'ω' => 'w', 'ά' => 'a', 'έ' => 'e', 'ί' => 'i', 'ό' => 'o', 'ύ' => 'y', 'ή' => 'h',
			'ώ' => 'w', 'ς' => 's', 'ϊ' => 'i', 'ΰ' => 'y', 'ϋ' => 'y', 'ΐ' => 'i',
		];
		$string = strtr($string, $replace);
		$string = preg_replace('/[^\p{L}\p{Nd}\.]+/u', $delimiter, $string);
		return trim($string, $delimiter);
	}

	/**
	 * Change the order of associative array.
	 *
	 * @param array $array
	 * @param array $order
	 *
	 * @return array
	 */
	public static function changeSequence(array $array, array $order): array
	{
		if (!$order) {
			return $array;
		}
		$returnLinks = [];
		foreach ($order as $value) {
			if ($array[$value]) {
				$returnLinks[$value] = $array[$value];
			}
			unset($array[$value]);
		}
		return array_merge($returnLinks, $array);
	}

	/**
	 * Get locks content by events.
	 *
	 * @param array $locks
	 *
	 * @return string
	 */
	public static function getLocksContent(array $locks): string
	{
		$return = '';
		foreach ($locks as $lock) {
			switch ($lock) {
				case 'copy':
					$return .= ' oncopy = "return false"';
					break;
				case 'cut':
					$return .= ' oncut = "return false"';
					break;
				case 'paste':
					$return .= ' onpaste = "return false"';
					break;
				case 'contextmenu':
					$return .= ' oncontextmenu = "return false"';
					break;
				case 'selectstart':
					$return .= ' onselectstart = "return false" onselect = "return false"';
					break;
				case 'drag':
					$return .= ' ondragstart = "return false" ondrag = "return false"';
					break;
			}
		}
		return $return;
	}

	/**
	 * Recursively searches array, returns top or all keys.
	 * 
	 * @param $needle
	 * @param array $haystack
	 * @param bool $returnAll
	 * 
	 */
	public static function recursive_array_search($needle, $haystack, $returnAll = false) {
		$result = [];
		foreach($haystack as $key=>$value) {
				$current_key=$key;
				if($needle===$value OR (is_array($value) && self::recursive_array_search($needle,$value, $returnAll) !== false)) {
					if (!$returnAll) {
						return $current_key;
					} else {
						$result[] = $current_key;
					}
				}
		}
		return !empty($result) ? $result : false;
	}

	public static function process($operation, $workingDir = null, $ignoreError = false, $timeout = 120) {
		\App\Log::warning("App::Utils::process:$operation in $workingDir");
		
		$descriptorspec = [["pipe", "r"], ["pipe", "w"], ["pipe", "w"]];
		$process = proc_open($operation, $descriptorspec, $pipes, $workingDir);

		if (!is_resource($process)) {
			throw new \Error("Cannot open process");
		}

		stream_set_blocking($pipes[1], false);
		stream_set_blocking($pipes[2], false);

		$start = time();
		$terminated = false;
		$output = '';
		$error = '';
		do {
			$status = proc_get_status($process);

			$output .= stream_get_contents($pipes[1]);
			$error .= stream_get_contents($pipes[2]);

			if (!$status['running']) {
				break; // Process completed in time
			}

			if ($timeout > 0 && time() - $start > $timeout) {
				proc_terminate($process); // Time exceeded, terminate process
				$error .= PHP_EOL . "Process terminated after $timeout seconds";
				$terminated = true;
				break;
			}

			sleep(1);
		} while (true);
		
		if (!$terminated) {
			foreach ($pipes as $pipe) {
				fclose($pipe);
			}

			proc_close($process);
		}

		if ($ignoreError === false && !empty($error)) {
			\App\Log::error("App::Utils::process <- " . $error);
			throw new \Error($error);
		} else if (\is_array($ignoreError) && !empty($error)) {
			$anyMatched = false;
			foreach ($ignoreError as $errorPattern) {
				if (preg_match($errorPattern, $error) !== false) {
					$anyMatched = true;
					break;
				}
			}

			if (!$anyMatched) {
				\App\Log::error("App::Utils::process <- " . $error);
				throw new \Error($error);
			}
		}

		return "O: $output, E: $error";
	}

	/**
	 * Compare two strings case insensitive no whitespaces, empty !== !empty
	 * 
	 * @param $string a
	 * @param $string b
	 * 
	 */
	public static function str_equal($a, $b) {
		$strA = strtolower(preg_replace('/\s+/', '', $a ?: ""));
		$strB = strtolower(preg_replace('/\s+/', '', $b ?: ""));

		return $strA === $strB;
	}

	/**
	 * Get the county from the address
	 * 
	 * @param $address
	 * 
	 * @return string County ID from county dictionary
	 * 
	 * @throws \Exception Geocoding failed or county not found in counties dictionary
	 */
	public static function getCounty( string $address )
	{
		\App\Log::warning("Utils::getCounty:$address");

		$url = "https://atlas.microsoft.com/search/address/json?api-version=1.0&countrySet=US&limit=1&query=$address";
    $requestOptions = \App\RequestHttp::getOptions();
    $requestOptions['headers']['Authorization'] = \App\Config::api('azureAddressKey');

    \App\Log::warning("Utils::getCounty:$url");

    $result = (new \GuzzleHttp\Client($requestOptions))->get($url, ['timeout' => 20, 'connect_timeout' => 10]);
    if (200 == $result->getStatusCode()) {
      $response = $result->getBody();
    } else {
      throw new \Exception("Azure Address request failed: {$result->getStatusCode()}/{$result->getReasonPhrase()}");
    }

    $json = \App\Json::decode($response);

    if ($json['summary']['numResults'] < 1) {
      // error, not found
			\App\Log::warning("Utils::getCounty:$response");
      throw new \Exception("Could not geolocate address '$address'");
    } else {
      $countyName = $json['results'][0]['address']['countrySecondarySubdivision'];
			
			\App\Log::warning("Utils::getCounty:$countyName");
        
      // find county in Counties
      $county = (new \App\QueryGenerator('Counties'))
        ->setField('id')
        ->addCondition('county', $countyName, 's')
        ->createQuery()
        ->scalar();

      if (empty($county)) {
				throw new \Exception("County $countyName not found in Counties dictionary");
      }

			\App\Log::warning("Utils::getCounty:$county");
			return $county;
		}
	}
}

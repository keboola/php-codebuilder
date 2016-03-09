<?php
/**
 * Created by Ondrej Vana <kachna@keboola.com>
 * Date: 09/12/14
 */

namespace Keboola\Code;

use	Keboola\Code\Exception\UserScriptException;
use Keboola\Utils\Utils;

class Builder
{
	/**
	 * @var array
	 */
	protected $allovedFns;

	public function __construct(
		array $allowedFns = [
			"md5",
			"sha1",
			"time",
			"date",
			"strtotime",
			"base64_encode",
			"hash_hmac",
			"sprintf",
			"concat",
			"implode"
		]
	) {
		$this->allovedFns = $allowedFns;
	}

	/**
	 * @param \stdClass $object
	 * @param array $params Array of arrays!
	 * 	Accessed as {"attr": "key"} to access $params['attr']['key']
	 * 	From second level onwards the array is flattenned and keys
	 * 	concatenated by '.' (eg $params['attr']['nested.key'])
	 * @return mixed
	 * @api
	 */
	public function run(\stdClass $object, array $params = [])
	{
		// Flatten $params from 2nd level onwards
		array_walk($params, function(&$value) {
			if(!is_array($value))
			{
				throw new \Exception("The params for code builder must be an array of arrays!");
			}
			$value = Utils::flattenArray($value);
		});
		return $this->buildFunction($object, $params);
	}

	/**
	 * @param mixed $object
	 * @param array $params Array of arrays!
	 * 	Accessed as {"attr": "key"} to access $params['attr']['key']
	 * 	From second level onwards the array is flattenned and keys
	 * 	concatenated by '.' (eg $params['attr']['nested.key'])
	 * @return mixed
	 */
	protected function buildFunction($object, array $params = [])
	{
        if (is_array($object)) {
            $array = [];
            foreach($object as $k => $v) {
                $array[$k] = $this->buildFunction($v, $params);
            }

            return $array;
        } elseif (!is_object($object)) {
			return $object;
		} elseif (property_exists($object, 'function')) {
			if (!in_array($object->function, $this->allovedFns)) {
				$func = is_scalar($object->function) ? $object->function : json_encode($object->function);
				throw new UserScriptException("Illegal function '{$func}'!");
			}

			$args = [];
			if (property_exists($object, 'args')) {
				foreach($object->args as $k => $v) {
					$args[] = $this->buildFunction($v, $params);
				}
			}

			try {
				if (!function_exists($object->function)) {
					return $this->customFunction($object->function, $args);
				} else {
					return call_user_func_array($object->function, $args);
				}
			} catch(\ErrorException $e) {
				// Error handler must be set for this to work properly
				throw new UserScriptException($e->getMessage());
			}
		} elseif (count($object) == 1 && array_key_exists(key($object), $params)) {
			if (!isset($params[key($object)][reset($object)])) {
				throw new UserScriptException(sprintf("Error evaluating user function - %s '%s' not found!", key($object), reset($object)));
			}
			return $params[key($object)][reset($object)];
		} else {
			return $object;
		}
	}

	/**
	 * @param string $fn
	 * @param array $args
	 * @return mixed
	 */
	protected function customFunction($fn, $args)
	{
		if (method_exists($this, $fn)) {
			return $this->{$fn}($args);
		} else {
			throw new \Exception("Attempted to call undefined method {$fn}.", 500);
		}
	}

	/**
	 * Concatenate multiple strings into one
	 * @param array $args
	 * @return string
	 */
	protected function concat(array $args)
	{
		return implode('', $args);
	}

	/**
	 * @param string $function
	 * @return Builder
	 */
	public function allowFunction($function)
	{
		if (!in_array($function, $this->allovedFns)) {
			$this->allovedFns[] = $function;
		}
		return $this;
	}

	/**
	 * @param string $function
	 * @return Builder
	 */
	public function denyFunction($function)
	{
		foreach (array_keys($this->allovedFns, $function) as $key) {
			unset($this->allovedFns[$key]);
		}
		return $this;
	}
}

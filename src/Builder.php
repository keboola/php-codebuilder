<?php

declare(strict_types=1);

namespace Keboola\Code;

use Exception;
use Keboola\Code\Exception\UserScriptException;
use stdClass;
use Throwable;
use function Keboola\Utils\flattenArray;

class Builder
{
    /**
     * @var array|string[]
     */
    protected array $allowedFns;

    public function __construct(
        array $allowedFns = [
            'md5',
            'sha1',
            'time',
            'date',
            'strtotime',
            'base64_encode',
            'hash_hmac',
            'sprintf',
            'concat',
            'ifempty',
            'implode',
            'hash',
        ]
    ) {
        $this->allowedFns = $allowedFns;
    }

    /**
     * @param array $params Array of arrays!
     *    Accessed as {"attr": "key"} to access $params['attr']['key']
     *    From second level onwards the array is flattenned and keys
     *    concatenated by '.' (eg $params['attr']['nested.key'])
     * @return mixed
     * @throws UserScriptException
     */
    public function run(stdClass $object, array $params = [])
    {
        // Flatten $params from 2nd level onwards
        array_walk($params, function (&$value): void {
            if (!is_array($value)) {
                throw new Exception('The params for code builder must be an array of arrays!');
            }
            $value = flattenArray($value);
        });
        return $this->buildFunction($object, $params);
    }

    /**
     * @param mixed $object
     * @param array $params Array of arrays!
     *    Accessed as {"attr": "key"} to access $params['attr']['key']
     *    From second level onwards the array is flattenned and keys
     *    concatenated by '.' (eg $params['attr']['nested.key'])
     * @return mixed
     * @throws UserScriptException
     */
    protected function buildFunction($object, array $params = [])
    {
        if (is_array($object)) {
            // this is used when function arguments are array - e.g. implode function
            $array = [];
            foreach ($object as $k => $v) {
                $array[$k] = $this->buildFunction($v, $params);
            }

            return $array;
        } elseif (is_object($object)) {
            if (property_exists($object, 'function')) {
                // a function object
                if (!in_array($object->function, $this->allowedFns)) {
                    $func = is_scalar($object->function) ? $object->function : json_encode($object->function);
                    throw new UserScriptException("Illegal function '{$func}'!");
                }

                $args = [];
                if (property_exists($object, 'args')) {
                    foreach ($object->args as $k => $v) {
                        $args[] = $this->buildFunction($v, $params);
                    }
                }

                try {
                    if (!function_exists($object->function)) {
                        return $this->customFunction($object->function, $args);
                    } else {
                        return call_user_func_array($object->function, $args);
                    }
                } catch (Throwable $e) {
                    throw new UserScriptException($e->getMessage());
                }
            } elseif ((count(get_object_vars($object)) === 1) &&
                array_key_exists(key(get_object_vars($object)), $params)
            ) {
                // reference to function context
                $objectArray = get_object_vars($object);
                $prop = key($objectArray);
                $value = $object->$prop;
                if (is_object($value)) {
                    throw new UserScriptException(sprintf(
                        "Error evaluating user function - %s '%s' is not a string!",
                        $prop,
                        json_encode($value)
                    ));
                }
                if (!isset($params[$prop][$value])) {
                    throw new UserScriptException(
                        sprintf("Error evaluating user function - %s '%s' not found!", $prop, $value)
                    );
                }

                return $params[key($objectArray)][reset($objectArray)];
            } else {
                // an object which is not a function, recurse inside to see if there are any functions in it
                foreach (get_object_vars($object) as $key => $value) {
                    $object->$key = $this->buildFunction($value, $params);
                }
                return $object;
            }
        } else {
            // scalar
            return $object;
        }
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    protected function customFunction(string $fn, array $args)
    {
        if (method_exists($this, $fn)) {
            return $this->{$fn}($args);
        } else {
            throw new Exception("Attempted to call undefined method {$fn}.", 500);
        }
    }

    /**
     * Concatenate multiple strings into one
     */
    protected function concat(array $args): string
    {
        return implode('', $args);
    }

    /**
     * Return first argument if is not empty, otherwise return second argument
     * @return mixed
     * @throws UserScriptException
     */
    protected function ifempty(array $args)
    {
        if (count($args) !== 2) {
            throw new UserScriptException("Bad argument count for function 'ifempty'!");
        }

        return empty($args[0]) ? $args[1] : $args[0];
    }

    public function allowFunction(string $function): Builder
    {
        if (!in_array($function, $this->allowedFns)) {
            $this->allowedFns[] = $function;
        }
        return $this;
    }

    public function denyFunction(string $function): Builder
    {
        foreach (array_keys($this->allowedFns, $function) as $key) {
            unset($this->allowedFns[$key]);
        }
        return $this;
    }
}

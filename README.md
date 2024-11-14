# Keboola CodeBuilder

## Description
Execute user scripts defined in a JSON

## Allowed functions

- `md5`: Generate a md5 key from its argument value
- `sha1`: Generate a sha1 key from its argument value
- `time`: Return time from the beginning of the unix epoch in seconds (1.1.1970)
- `date`: Return date in a specified format
- `strtotime`: Convert a date string to number of seconds from the beginning of the unix epoch
- `base64_encode`
- `hash_hmac`: [See PHP documentation](http://php.net/manual/en/function.hash-hmac.php)
- `sprintf`: [See PHP documentation](http://php.net/manual/en/function.sprintf.php)
- `concat`: Concatenate its arguments into a single string
- `ifempty`: Return first argument if is not empty, otherwise return second argument
- `implode`: Concatenate an array from the second argument, using glue string from the first arg
- `hash`: [See PHP documentation](https://www.php.net/manual/en/function.hash.php)

## Syntax
The function must be specified in a JSON format, which may contain one of the following 4 objects:

- **String**: `{ "something" }`
- **Function**: One of the allowed functions above
    - Example (this will return current date in this format: `2014-12-08+09:38`:

        ```
        {
            "function": "date",
            "args": [
                "Y-m-d+H:i"
            ]
        }
        ```

    - Example with a nested function (will return a date in the same format from 3 days ago):

        ```
        {
            "function": "date",
            "args": [
                "Y-m-d+H:i",
                {
                    "function": "strtotime",
                    "args": ["3 days ago"]
                }
            ]
        }
        ```
- **A key from the parameters array**:
	- `{ "attr": "attributeName" }` for $params['attr']['attributeName']
	- or `{ "param": "nested.attribute.name" }` for $params['param']['nested']['attribute']['name']
	- The *first* level is always used as the key to determine the "type"




## License

MIT licensed, see [LICENSE](./LICENSE) file.

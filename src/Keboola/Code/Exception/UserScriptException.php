<?php
namespace Keboola\Code\Exception;

class UserScriptException extends \Exception
{
	public function __construct($message)
	{
		parent::__construct($message, 400);
	}
}

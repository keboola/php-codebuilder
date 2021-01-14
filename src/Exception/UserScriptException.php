<?php

declare(strict_types=1);

namespace Keboola\Code\Exception;

class UserScriptException extends \Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message, 400);
    }
}

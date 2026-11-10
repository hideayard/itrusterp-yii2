<?php

namespace app\components;

use yii\web\HttpException;

class ValidationException extends HttpException
{
    public array $errors;

    public function __construct(array $errors = [], string $message = 'The given data was invalid.', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($code, $message, $code, $previous);
        $this->errors = $errors;
    }
}

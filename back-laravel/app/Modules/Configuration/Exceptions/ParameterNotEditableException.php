<?php

declare(strict_types=1);

namespace App\Modules\Configuration\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ParameterNotEditableException extends Exception
{
    public function __construct(string $message = 'El parámetro no es editable.')
    {
        parent::__construct($message, 422);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'value' => [$this->getMessage()],
            ],
        ], 422);
    }
}

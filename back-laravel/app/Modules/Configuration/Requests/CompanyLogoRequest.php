<?php

declare(strict_types=1);

namespace App\Modules\Configuration\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'logo' => [
                'required',
                'file',
                'mimes:png,jpg,jpeg,webp,svg',
                'max:2048',
            ],
        ];
    }
}

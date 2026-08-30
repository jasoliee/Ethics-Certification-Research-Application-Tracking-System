<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'profile_image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048', 'dimensions:min_width=120,min_height=120,max_width=4096,max_height=4096'],
        ];
    }
}

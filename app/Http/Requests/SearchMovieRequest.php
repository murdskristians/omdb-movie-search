<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SearchMovieRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function searchQuery(): string
    {
        return $this->validated('q');
    }

    public function page(): int
    {
        return $this->integer('page', 1);
    }
}

<?php

namespace App\Http\Requests;

use App\Domain\Enums\Channel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessMessageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'content' => ['required', 'string', 'min:10'],
            'channels' => ['required', 'array', 'min:1', 'distinct'],
            'channels.*' => ['required', 'string', Rule::in(Channel::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'channels.required' => 'Selecciona al menos un canal de distribución.',
            'channels.min' => 'Selecciona al menos un canal de distribución.',
            'channels.*.in' => 'Canal no válido. Los canales permitidos son: '.implode(', ', Channel::values()).'.',
        ];
    }
}

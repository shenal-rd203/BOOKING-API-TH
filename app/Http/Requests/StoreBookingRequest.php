<?php

namespace App\Http\Requests;

use App\Models\Booking;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'slot' => ['required', Rule::in(Booking::SLOTS)],
        ];
    }

    public function messages(): array
    {
        return [
            'date.after_or_equal' => 'The selected date shouldn\'t be a past date.',
            'slot.in' => 'The selected slot is invalid. Available slots: ' . implode(', ', Booking::SLOTS),
        ];
    }
}

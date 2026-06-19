<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id ?? $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'nim' => [
                Rule::requiredIf(fn (): bool => $this->input('role') === User::ROLE_USER),
                'nullable',
                'string',
                'digits:11',
                Rule::unique('users', 'nim')->ignore($userId),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => [
                'required',
                'string',
                'max:20',
                'regex:/^(?:\+62\d{8,13}|0\d{8,13})$/',
            ],
            'role' => ['required', 'string', Rule::in([
                User::ROLE_SUPER_ADMIN,
                User::ROLE_ADMIN_BAP,
                User::ROLE_ADMIN_SARPRAS,
                User::ROLE_USER,
            ])],
            'password' => [
                $this->routeIs('admin.users.store') ? 'required' : 'nullable',
                'string',
                'min:8',
                'confirmed',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $nim = trim((string) $this->input('nim', ''));

        $this->merge([
            'nim' => $this->input('role') === User::ROLE_USER && $nim !== ''
                ? $nim
                : null,
        ]);
    }

    /**
     * Custom attribute names for validation errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'nim' => 'NIM',
            'email' => 'email',
            'phone' => 'nomor telepon',
            'role' => 'role',
            'password' => 'password',
        ];
    }

    public function messages(): array
    {
        return [
            'nim.required' => 'NIM wajib diisi untuk mahasiswa.',
            'nim.digits' => 'NIM harus terdiri dari tepat 11 digit angka.',
            'nim.unique' => 'NIM sudah digunakan oleh mahasiswa lain.',
        ];
    }
}

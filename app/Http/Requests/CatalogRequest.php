<?php

namespace App\Http\Requests;

use App\Support\Catalogs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission($this->route('catalog').'.manage');
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        if (is_string($this->name)) {
            $values['name'] = Catalogs::normalize($this->name);
        }
        if (is_string($this->code)) {
            $values['code'] = mb_strtoupper(Catalogs::normalize($this->code));
        }
        $this->merge($values);
    }

    public function rules(): array
    {
        $key = $this->route('catalog');
        $rules = ['name' => ['required', 'string', $key === 'units' ? 'max:100' : 'max:120'], 'description' => ['nullable', 'string', 'max:2000'], 'active' => ['required', 'boolean']];
        if (in_array($key, ['categories', 'units'])) {
            $rules['code'] = ['required', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/', Rule::unique($key, 'code')->ignore($this->route('id'))];
        }
        if ($key === 'subcategories') {
            $rules['category_id'] = ['required', 'integer', 'exists:categories,id'];
        }
        if ($key === 'units') {
            $rules['abbreviation'] = ['required', 'string', 'max:15'];
            $rules['decimal_places'] = ['required', 'integer', 'between:0,3'];
        }

        return $rules;
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Support\Catalogs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('POST')
            ? $this->user()->can('create', Product::class)
            : $this->user()->can('update', $this->route('product'));
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        if (is_string($this->sku)) {
            $data['sku'] = mb_strtoupper(Catalogs::normalize($this->sku));
        }
        if (is_string($this->name)) {
            $data['name'] = Catalogs::normalize($this->name);
        }
        if (is_string($this->barcode)) {
            $barcode = trim($this->barcode);
            $data['barcode'] = $barcode === '' ? null : $barcode;
        }
        $this->merge($data);
    }

    public function rules(): array
    {
        $id = $this->route('product')?->id;

        return [
            'sku' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/', Rule::unique('products')->ignore($id)],
            'barcode' => ['nullable', 'string', 'max:80', Rule::unique('products')->ignore($id)],
            'name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'], 'subcategory_id' => ['nullable', 'integer', 'exists:subcategories,id'],
            'unit_id' => ['required', 'integer', 'exists:units,id'], 'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'presentation' => ['nullable', 'string', 'max:150'], 'location' => ['nullable', 'string', 'max:150'],
            'sale_price' => ['required', 'string', 'max:30'], 'minimum_stock' => ['required', 'string', 'max:30'],
            'reference_cost' => [$this->user()->hasPermission('products.view_cost') ? 'required' : 'prohibited', 'string', 'max:30'],
            'active' => ['prohibited'], 'stock' => ['prohibited'], 'quantity' => ['prohibited'], 'warehouse_id' => ['prohibited'], 'actor_id' => ['prohibited'],
        ];
    }
}

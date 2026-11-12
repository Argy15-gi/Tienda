<?php

namespace App\Http\Requests;

use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->esAdmin();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('sku')) {
            $this->merge([
                'sku' => strtoupper(trim(strip_tags((string) $this->input('sku', '')))),
            ]);
        }

        if ($this->has('nombre')) {
            $this->merge([
                'nombre' => trim(strip_tags((string) $this->input('nombre', ''))),
            ]);
        }

        if ($this->has('categoria')) {
            $categoria = trim(strip_tags((string) $this->input('categoria', '')));
            $this->merge([
                'categoria' => $categoria !== '' ? $categoria : 'General',
            ]);
        }

        if ($this->has('descripcion') && $this->input('descripcion') !== null) {
            $this->merge([
                'descripcion' => trim(strip_tags((string) $this->input('descripcion', ''))),
            ]);
        }
    }

    public function rules(): array
    {
        /** @var Producto|int|string|null $producto */
        $producto = $this->route('producto');
        $productoId = $producto instanceof Producto ? $producto->id : $producto;

        return [
            'sku' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('productos', 'sku')->ignore($productoId),
            ],
            'nombre' => ['required', 'string', 'min:2', 'max:160'],
            'categoria' => ['required', 'string', 'max:80'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'precio_centavos' => ['required', 'integer', 'min:1', 'max:100000000'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.required' => 'El código SKU es obligatorio.',
            'sku.regex' => 'El SKU solo puede contener letras, números, puntos, guiones y guion bajo.',
            'sku.unique' => 'Ese código SKU ya está registrado en otro producto.',
            'nombre.required' => 'El nombre del producto es obligatorio.',
            'nombre.min' => 'El nombre debe tener al menos 2 caracteres.',
            'nombre.max' => 'El nombre no puede superar los 160 caracteres.',
            'categoria.required' => 'La categoría es obligatoria.',
            'precio_centavos.required' => 'El precio en centavos es obligatorio.',
            'precio_centavos.integer' => 'El precio debe ser un número entero en centavos.',
            'precio_centavos.min' => 'El precio debe ser mayor a 0 centavos.',
            'stock.required' => 'El stock inicial/actual es obligatorio.',
            'stock.integer' => 'El stock debe ser un número entero.',
            'stock.min' => 'El stock no puede ser negativo.',
        ];
    }
}

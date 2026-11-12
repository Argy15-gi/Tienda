<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('nota') && $this->input('nota') !== null) {
            $this->merge([
                'nota' => trim(strip_tags((string) $this->input('nota', ''))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:1000'],
            'nota' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'producto_id.required' => 'Debes elegir un producto válido.',
            'producto_id.exists' => 'El producto seleccionado no existe.',
            'cantidad.required' => 'Indica cuántas unidades deseas pedir.',
            'cantidad.integer' => 'La cantidad debe ser un número entero.',
            'cantidad.min' => 'Debes pedir al menos 1 unidad.',
            'nota.max' => 'La nota del pedido no puede exceder 500 caracteres.',
        ];
    }
}

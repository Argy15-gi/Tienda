<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ($this->user()->esVendedor() || $this->user()->esAdmin());
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();
        if (is_array($input['items'] ?? null)) {
            $input['items'] = array_values(array_filter(
                $input['items'],
                fn ($item) => is_array($item) && isset($item['producto_id']) && $item['producto_id'] !== '',
            ));
        }
        $this->replace($input);
    }

    public function rules(): array
    {
        return [
            'comprador_id' => ['nullable', 'integer', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.producto_id' => ['required', 'integer', 'distinct', 'exists:productos,id'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:1000000'],
            // Facturación y cobro en caja
            'metodo_pago' => ['nullable', 'string', 'in:efectivo,qr,transferencia'],
            'monto_pagado' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'cliente_nombre' => ['nullable', 'string', 'max:100'],
            'nit_ci' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Debes seleccionar al menos un producto para registrar la venta.',
            'items.min' => 'Debes incluir al menos un producto en la venta.',
            'items.*.producto_id.distinct' => 'No repitas el mismo producto en varias filas; suma la cantidad en una sola línea.',
            'items.*.producto_id.exists' => 'Uno de los productos seleccionados no existe.',
            'items.*.cantidad.min' => 'La cantidad mínima por producto es 1.',
            'metodo_pago.in' => 'El método de pago solo puede ser efectivo, QR o transferencia.',
            'monto_pagado.numeric' => 'El monto recibido debe ser un número.',
            'monto_pagado.min' => 'El monto recibido no puede ser negativo.',
            'cliente_nombre.max' => 'El nombre del cliente no puede superar los 100 caracteres.',
            'nit_ci.max' => 'El NIT/CI no puede superar los 20 caracteres.',
        ];
    }
}

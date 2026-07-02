<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Suscripcion extends Model
{
    protected $connection = 'tenant';

    protected $table = 'suscripciones';

    protected $fillable = [
        'plan',
        'billing',
        'estado',
        'proxima_factura',
        'historial_pagos',
        'metodo_pago',
    ];

    protected function casts(): array
    {
        return [
            'proxima_factura' => 'date',
            'historial_pagos' => 'array',
            'metodo_pago' => 'array',
        ];
    }
}

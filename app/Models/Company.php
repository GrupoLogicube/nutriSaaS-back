<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'owner_user_id',
        'nombre',
        'logo_path',
        'nombre_bd',
        'estado'
    ];

    public function ownerUser()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}

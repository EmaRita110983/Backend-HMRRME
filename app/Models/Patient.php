<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'admin_id',
        'created_by',
        'first_name',
        'last_name',
        'cedula',
        'pasaporte',
        'birth_date',
        'phone',
        'email',
        'address',
        'insurance',
        'emergency_contact',
        'emergency_phone',
        'medical_conditions',
    ];


    /**
     * Doctor dueño del paciente.
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }


    /**
     * Usuario que registró el paciente.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function historialMedico()
    {
        return $this->hasMany(HistorialMedico::class);
    }

    public function recetas()
    {
        return $this->hasMany(Receta::class);
    }

    public function citas()
    {
        return $this->hasMany(Cita::class);
    }
}

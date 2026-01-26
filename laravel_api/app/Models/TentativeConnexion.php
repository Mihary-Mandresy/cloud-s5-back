<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TentativeConnexion extends Model
{
    use HasFactory;

    protected $table = 'tentatives_connexion';
    protected $primaryKey = 'id';
    
    protected $fillable = ['email', 'ip', 'nombre_tentative'];
    
    protected $casts = [
        'date_tentative' => 'datetime',
    ];
}
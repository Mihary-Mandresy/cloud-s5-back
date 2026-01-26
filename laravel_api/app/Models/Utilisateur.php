<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Utilisateur extends Authenticatable implements JWTSubject
{
    use HasFactory;

    protected $table = 'utilisateurs';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'email',
        'mot_de_passe',
        'role',
        'nom',
        'est_bloque',
        'tentatives_connexion',
        'derniere_tentative',
        'firebase_uid'
    ];

    protected $hidden = [
        'mot_de_passe',
    ];

    protected $casts = [
        'date_inscription' => 'datetime',
        'derniere_tentative' => 'datetime',
        'est_bloque' => 'boolean',
    ];

    // JWT methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'role' => $this->role,
            'email' => $this->email,
        ];
    }

    public function getAuthPassword()
    {
        return $this->mot_de_passe;
    }

    // Relationships
    public function roleRelation()
    {
        return $this->belongsTo(Role::class, 'role');
    }

    public function tentativesConnexion()
    {
        return $this->hasMany(TentativeConnexion::class, 'email', 'email');
    }
}
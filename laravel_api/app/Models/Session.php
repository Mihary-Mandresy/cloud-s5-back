<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    protected $table = 'sessions';
    
    // La clé primaire n'est PAS 'id' mais 'token_session'
    protected $primaryKey = 'token_session';
    public $incrementing = false;
    protected $keyType = 'string';
    
    // Désactiver les timestamps automatiques
    public $timestamps = false;
    
    protected $dates = [
        'date_creation',
        'date_expiration'
    ];
    
    protected $casts = [
        'date_creation' => 'datetime',
        'date_expiration' => 'datetime',
        'last_activity' => 'integer'
    ];
    
    protected $fillable = [
        'token_session',
        'utilisateur_id',
        'date_creation',
        'date_expiration',
        'payload',
        'last_activity',
        'ip_address',
        'user_agent'
    ];
    
    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id');
    }
}
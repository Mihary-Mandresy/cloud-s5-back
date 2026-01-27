<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Signalement extends Model
{
    use HasFactory;

    protected $table = 'signalements';
    
    protected $fillable = [
        'titre',
        'description',
        'latitude',
        'longitude',
        'date_modification',
        'statut',
        'surface_m2',
        'budget',
        'avancement',
        'entreprise_responsable',
        'utilisateur_id',
        'synchronise_firebase'
    ];
    
    protected $casts = [
        'synchronise_firebase' => 'boolean',
        'date_creation' => 'datetime',
        'date_modification' => 'datetime',
    ];
    
    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class);
    }
    
    public function historiques()
    {
        return $this->hasMany(HistoSignalement::class);
    }
}
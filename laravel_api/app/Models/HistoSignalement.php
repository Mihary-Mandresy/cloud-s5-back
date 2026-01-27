<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoSignalement extends Model
{
    use HasFactory;

    protected $table = 'histo_signalements';
    
    protected $fillable = ['signalement_id', 'statut'];
    
    protected $casts = [
        'date_chargement' => 'datetime',
    ];
    
    public function signalement()
    {
        return $this->belongsTo(Signalement::class);
    }
}
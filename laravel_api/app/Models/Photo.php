<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Photo extends Model
{
    use HasFactory;

    protected $table = 'photos';
    
    protected $fillable = [
        'signalement_id',
        'image_base64',
        'mime_type',
        'nom_fichier',
        'ordre'
    ];
    
    public function signalement()
    {
        return $this->belongsTo(Signalement::class);
    }
}
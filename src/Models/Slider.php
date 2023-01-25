<?php

namespace Zdslab\Laravelinit\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Slider extends Model {
    use HasFactory;

    public function boutons(){
        return $this->belongsToMany(Bouton::class);
    }
}
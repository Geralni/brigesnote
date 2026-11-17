<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    /** @use HasFactory<\Database\Factories\RegionFactory> */
    use HasFactory;

    protected $table = 'regions';
    protected $primaryKey = 'idRegion';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'idRegion',
        'nombreRegion',
        'pointX',
        'pointY',
        'zoom',
    ];
}

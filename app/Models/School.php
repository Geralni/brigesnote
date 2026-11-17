<?php

namespace App\Models;

use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

    protected $table = 'schools';
    protected $primaryKey = 'codMod';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'codMod',
        'idDistrito',
        'nombreCenEdu',
        'cenPob',
        'dirCen',
        'director',
        'dis_crecer',
        'dis_juntos',
    ];
}

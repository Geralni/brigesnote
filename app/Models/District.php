<?php

namespace App\Models;

use Database\Factories\DistrictFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    /** @use HasFactory<DistrictFactory> */
    use HasFactory;

    protected $table = 'districts';
    protected $primaryKey = 'idDistrito';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'idDistrito',
        'nombreDistrito',
    ];
}

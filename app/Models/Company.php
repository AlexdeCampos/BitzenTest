<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';


     protected $fillable = [
        'social_name',
        'legal_name',
        'document_number',
        'creation_date',
        'responsible_email',
        'responsible_name',
    ];
}

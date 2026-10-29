<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = [
        'company_name',
        'slogan',
        'contact_email',
        'phone',
        'address',
        'website',
        'default_currency',
        'updated_by',
    ];
}

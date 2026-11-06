<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = ['restaurant_id', 'name', 'contact_person', 'phone', 'email', 'address', 'tin', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}

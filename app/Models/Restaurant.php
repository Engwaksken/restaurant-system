<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Restaurant extends Model
{
    protected $fillable = [
        'name', 'tagline', 'about', 'code', 'phone', 'email', 'address', 'tin', 'currency', 'timezone',
        'logo_path', 'favicon_path', 'hero_image_path', 'primary_color', 'accent_color',
        'opening_hours', 'website_url', 'facebook_url', 'instagram_url', 'whatsapp_number', 'email_footer',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}

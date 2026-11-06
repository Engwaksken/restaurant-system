<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class MenuCategory extends Model {
    protected $fillable=['restaurant_id','name','slug','description','image_path','display_order','is_active'];
    protected $casts=['is_active'=>'boolean'];
    public function items(): HasMany { return $this->hasMany(MenuItem::class); }
}

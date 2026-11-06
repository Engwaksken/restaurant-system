<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Restaurant extends Model {
    protected $fillable = ['name','code','phone','email','address','tin','currency','timezone','logo_path','is_active'];
    protected $casts = ['is_active'=>'boolean'];
    public function branches(): HasMany { return $this->hasMany(Branch::class); }
}

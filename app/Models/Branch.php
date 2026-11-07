<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Branch extends Model {
    protected $fillable = ['restaurant_id','name','code','phone','email','address','is_active'];
    protected $casts = ['is_active'=>'boolean'];
    public function restaurant(): BelongsTo { return $this->belongsTo(Restaurant::class); }
    public function staff(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(User::class); }
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
class Order extends Model {
    use SoftDeletes;
    protected $fillable=['branch_id','customer_id','restaurant_table_id','waiter_id','order_number','order_type','status','subtotal','discount','tax','service_charge','delivery_fee','total','paid_amount','balance','notes','ordered_at','completed_at'];
    protected $casts=['subtotal'=>'decimal:2','discount'=>'decimal:2','tax'=>'decimal:2','service_charge'=>'decimal:2','delivery_fee'=>'decimal:2','total'=>'decimal:2','paid_amount'=>'decimal:2','balance'=>'decimal:2','ordered_at'=>'datetime','completed_at'=>'datetime'];
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
}

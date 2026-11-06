<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Customer extends Model { use SoftDeletes; protected $fillable=['restaurant_id','name','phone','email','address','date_of_birth','loyalty_points','notes']; protected $casts=['date_of_birth'=>'date']; }

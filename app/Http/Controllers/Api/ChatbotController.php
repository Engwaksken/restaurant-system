<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Http\{JsonResponse,Request};
class ChatbotController extends Controller {
    public function message(Request $request): JsonResponse {
        $data=$request->validate(['message'=>'required|string|max:1000']); $text=mb_strtolower($data['message']);
        if(str_contains($text,'menu') || str_contains($text,'food') || str_contains($text,'meal')) { $restaurant=Restaurant::where('code',config('restaurant.public_code'))->where('is_active',true)->firstOrFail(); $items=MenuItem::where('restaurant_id',$restaurant->id)->where('is_available',true)->whereHas('category',fn($query)=>$query->where('restaurant_id',$restaurant->id)->where('is_active',true))->orderBy('name')->limit(8)->get(['name','price']); return response()->json(['reply'=>'Here are some available items:','items'=>$items]); }
        if(str_contains($text,'close') || str_contains($text,'open')) return response()->json(['reply'=>'Please check the restaurant opening hours in Settings. A staff member can also assist you.']);
        return response()->json(['reply'=>'I can help with the menu, prices, orders, reservations and restaurant information. For account or refund issues, please request human support.']);
    }
}

<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{Branch,Customer,MenuCategory,MenuItem,Order};
use App\Services\ActivityLogger;
use App\Services\Inventory\RecipeInventoryService;
use Illuminate\Http\{RedirectResponse,Request};
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
class PosController extends Controller {
    public function index(): View { $user=auth()->user(); $this->authorizePos('view'); $restaurantId=(int)$user->restaurant_id; return view('pos.index',['categories'=>MenuCategory::with(['items'=>fn($q)=>$q->where('is_available',true)->where('restaurant_id',$restaurantId)])->where('is_active',true)->where('restaurant_id',$restaurantId)->orderBy('display_order')->get(),'branches'=>Branch::where('is_active',true)->where('restaurant_id',$restaurantId)->when($user->branch_id,fn($q)=>$q->where('id',$user->branch_id))->get(),'customers'=>Customer::where('restaurant_id',$restaurantId)->orderBy('name')->limit(200)->get()]); }
    public function checkout(Request $request, ActivityLogger $logger, RecipeInventoryService $inventory): RedirectResponse {
        $user=auth()->user(); $restaurantId=$user->restaurant_id;
        $this->authorizePos('checkout');
        abort_unless($restaurantId,403);
        $data=$request->validate(['branch_id'=>['required','integer',\Illuminate\Validation\Rule::exists('branches','id')->where(function($query) use($restaurantId,$user){$query->where('restaurant_id',$restaurantId);if($user->branch_id){$query->where('id',$user->branch_id);}})],'customer_id'=>['nullable','integer',\Illuminate\Validation\Rule::exists('customers','id')->where('restaurant_id',$restaurantId)],'order_type'=>'required|in:dine_in,takeaway,delivery,online','discount'=>'nullable|numeric|min:0','notes'=>'nullable|string','items'=>'required|array|min:1|max:50','items.*.menu_item_id'=>['required','integer',\Illuminate\Validation\Rule::exists('menu_items','id')->where('restaurant_id',$restaurantId)->where('is_available',true)],'items.*.quantity'=>'required|numeric|min:0.001|max:99']);
        $order=DB::transaction(function() use($data,$restaurantId,$user,$inventory){
            $menu=MenuItem::where('restaurant_id',$restaurantId)->where('is_available',true)->whereIn('id',collect($data['items'])->pluck('menu_item_id'))->lockForUpdate()->get()->keyBy('id'); abort_if($menu->count() !== collect($data['items'])->pluck('menu_item_id')->unique()->count(),422,'One or more items are unavailable.'); $subtotal=0;
            foreach($data['items'] as $line){ $subtotal+=(float)$menu[$line['menu_item_id']]->price*(float)$line['quantity']; }
            $discount=(float)($data['discount']??0);
            abort_if($discount > 0 && !$user->can('admin.pos.discount'), 403, 'You are not allowed to apply discounts.');
            abort_if($discount > $subtotal, 422, 'Discount cannot exceed the subtotal.');
            $taxableAmount=max(0,$subtotal-$discount);
            $tax=round($taxableAmount * max(0,min(100,(float)config('restaurant.tax_rate',0))) / 100,2);
            $deliveryFee=$data['order_type']==='delivery' ? max(0,(float)config('restaurant.delivery_fee',0)) : 0.0;
            $total=$taxableAmount+$tax+$deliveryFee;
            $order=Order::create(['branch_id'=>$data['branch_id'],'customer_id'=>$data['customer_id']??null,'waiter_id'=>auth()->id(),'order_number'=>'ORD-'.strtoupper((string)\Illuminate\Support\Str::uuid()),'order_type'=>$data['order_type'],'status'=>'confirmed','subtotal'=>$subtotal,'discount'=>$discount,'tax'=>$tax,'delivery_fee'=>$deliveryFee,'total'=>$total,'paid_amount'=>0,'balance'=>$total,'notes'=>$data['notes']??null,'ordered_at'=>now()]);
            foreach($data['items'] as $line){ $m=$menu[$line['menu_item_id']]; $qty=(float)$line['quantity']; $order->items()->create(['menu_item_id'=>$m->id,'item_name'=>$m->name,'unit_price'=>$m->price,'quantity'=>$qty,'total'=>(float)$m->price*$qty]); }
            $inventory->consume($restaurantId, (int) $data['branch_id'], $data['items'], (int) $user->id, (int) $order->id);
            return $order;
        });
        $logger->log('order.created',$order,['total'=>$order->total]); return redirect()->route('admin.orders.show',$order)->with('success','Order created successfully.');
    }

    private function authorizePos(string $operation): void
    {
        $user = auth()->user();
        abort_unless($user && $user->restaurant_id, 403);
        abort_unless(
            $user->can('admin.pos.' . $operation)
                || $user->can('admin.pos.manage')
                || $user->can('admin'),
            403
        );
    }
}

<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{Order,Payment};
use App\Services\ActivityLogger;
use Illuminate\Http\{RedirectResponse,Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class PaymentController extends Controller {
    public function store(Request $request, Order $order, ActivityLogger $logger): RedirectResponse {
        $user = auth()->user();
        abort_unless($user && $user->restaurant_id && ($user->can('orders.payments.create') || $user->can('orders.manage') || $user->can('admin')), 403);
        abort_unless($order->branch()->where('restaurant_id', $user->restaurant_id)->when($user->branch_id, fn ($query) => $query->whereKey($user->branch_id))->exists(), 404);
        $data=$request->validate(['payment_method'=>'required|in:cash,mtn_mobile_money,airtel_money,card,bank,flutterwave,pesapal,manual','amount'=>'required|numeric|min:1','transaction_reference'=>'nullable|string|max:255']);
        $payment=DB::transaction(function() use($data,$order){
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ((float) $data['amount'] > (float) $lockedOrder->balance) {
                throw ValidationException::withMessages(['amount' => 'Payment cannot exceed the outstanding balance.']);
            }
            $p=Payment::create(['order_id'=>$lockedOrder->id,'customer_id'=>$lockedOrder->customer_id,'received_by'=>auth()->id(),'receipt_number'=>'RCT-'.now()->format('ymdHis').'-'.random_int(100,999),'payment_method'=>$data['payment_method'],'amount'=>$data['amount'],'transaction_reference'=>$data['transaction_reference']??null,'status'=>'successful','paid_at'=>now()]);
            $paid=(float)$lockedOrder->payments()->where('status','successful')->sum('amount'); $lockedOrder->update(['paid_amount'=>$paid,'balance'=>max(0,(float)$lockedOrder->total-$paid),'status'=>$paid>=(float)$lockedOrder->total?'completed':$lockedOrder->status,'completed_at'=>$paid>=(float)$lockedOrder->total?now():$lockedOrder->completed_at]); return $p;
        });
        $logger->log('payment.received',$payment,['order'=>$order->order_number]); return back()->with('success','Payment recorded successfully.');
    }
}

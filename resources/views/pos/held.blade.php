@extends('layouts.app')

@section('title', 'Held Orders')
@section('page-title', 'Held Orders')

@section('content')
<div class="panel p-4">
    <p class="small text-muted">Held orders are not sent to the kitchen and do not use stock. Prices are recalculated when an order is resumed and created.</p>
    <div class="table-responsive"><table class="table align-middle">
        <thead><tr><th>Label</th><th>Items</th><th>Customer</th><th>Held by</th><th></th></tr></thead>
        <tbody>
        @forelse($held as $order)
            <tr>
                <td><strong>{{ $order->label }}</strong><small class="d-block text-muted">{{ $order->branch?->name }} · {{ \Illuminate\Support\Str::headline($order->order_type) }} · {{ $order->created_at->diffForHumans() }}</small></td>
                <td>{{ count($order->items) }} {{ \Illuminate\Support\Str::plural('line', count($order->items)) }}</td>
                <td>{{ $order->customer?->name ?? 'Walk-in' }}</td>
                <td>{{ $order->user?->name }}</td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-primary" href="{{ route('admin.pos.index', ['resume' => $order->id]) }}">Resume</a>
                    <form method="POST" action="{{ route('admin.pos.held.destroy', $order->id) }}" class="d-inline" onsubmit="return confirm('Discard this held order?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger" type="submit">Discard</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-muted">No held orders.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $held->links() }}
</div>
@endsection

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private function query(Request $request): Builder
    {
        $user = auth()->user();
        abort_unless($user && $user->restaurant_id, 403);
        abort_unless($user->can('reports.sales.export') || $user->can('reports.manage') || $user->can('admin'), 403);

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return Order::with(['customer', 'branch'])
            ->whereHas('branch', function ($query) use ($user): void {
                $query->where('restaurant_id', $user->restaurant_id);
                if ($user->branch_id) {
                    $query->whereKey($user->branch_id);
                }
            })
            ->when(isset($filters['from']), fn ($query) => $query->whereDate('ordered_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($query) => $query->whereDate('ordered_at', '<=', $filters['to']))
            ->latest();
    }

    public function salesCsv(Request $request): StreamedResponse
    {
        $orders = $this->query($request)->get();

        return response()->streamDownload(function () use ($orders): void {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Order', 'Customer', 'Type', 'Subtotal', 'Discount', 'Tax', 'Total', 'Paid', 'Balance', 'Status', 'Date']);
            foreach ($orders as $order) {
                fputcsv($file, [
                    $order->order_number, $order->customer?->name ?? 'Walk-in', $order->order_type,
                    $order->subtotal, $order->discount, $order->tax, $order->total,
                    $order->paid_amount, $order->balance, $order->status,
                    $order->ordered_at?->format('Y-m-d H:i'),
                ]);
            }
            fclose($file);
        }, 'sales-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function salesPdf(Request $request)
    {
        $orders = $this->query($request)->get();

        return Pdf::loadView('pdf.sales-report', compact('orders'))
            ->setPaper('a4', 'landscape')
            ->download('sales-' . now()->format('Ymd-His') . '.pdf');
    }
}

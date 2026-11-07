<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ScopesToUserBranches;
use App\Http\Controllers\Controller;
use App\Models\OrderFeedback;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Read-only customer order feedback for the user's restaurant/branch. */
class FeedbackController extends Controller
{
    use ScopesToUserBranches;

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403, 'Your account is not assigned to a restaurant.');
        abort_unless($user->can('customers.feedback.view') || $user->can('customers.manage') || $user->can('admin'), 403);
        $filters = $request->validate([
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $base = $this->whereUserBranch(OrderFeedback::query(), $user)
            ->when($filters['branch_id'] ?? null, fn ($query, $branchId) => $query->where('branch_id', $branchId));

        $summary = (clone $base)
            ->selectRaw('COUNT(*) as responses, AVG(rating) as average_rating, SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) as low_ratings')
            ->first();

        $feedback = (clone $base)
            ->when($filters['rating'] ?? null, fn ($query, $rating) => $query->where('rating', $rating))
            ->with(['branch:id,name', 'order:id,order_number', 'customer:id,name,phone'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.customers.feedback', [
            'feedback' => $feedback,
            'summary' => $summary,
            'branches' => $this->userBranches($user),
            'filters' => $filters,
            'canViewCustomers' => $user->can('customers.view') || $user->can('customers.manage') || $user->can('admin'),
        ]);
    }
}

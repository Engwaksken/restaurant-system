<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rule;

/** Restricts branch-owned records to the user's restaurant and, when assigned, branch. */
trait ScopesToUserBranches
{
    /** Constrain a query on a model with a `branch` relation. */
    protected function whereUserBranch(Builder $query, User $user): Builder
    {
        return $query->whereHas('branch', fn (Builder $branches) => $branches
            ->where('restaurant_id', $user->restaurant_id)
            ->when($user->branch_id, fn (Builder $scoped) => $scoped->whereKey($user->branch_id)));
    }

    /** Active branches the user may work in. */
    protected function userBranches(User $user): Collection
    {
        return Branch::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('is_active', true)
            ->when($user->branch_id, fn (Builder $query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** Validation rule: an active branch the user may work in. */
    protected function userBranchRule(User $user): Exists
    {
        return Rule::exists('branches', 'id')->where(fn ($query) => $query
            ->where('restaurant_id', $user->restaurant_id)
            ->where('is_active', true)
            ->when($user->branch_id, fn ($branches) => $branches->where('id', $user->branch_id)));
    }
}

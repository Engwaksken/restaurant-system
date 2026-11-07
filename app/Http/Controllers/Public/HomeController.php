<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\Branch;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $restaurant = Restaurant::query()
            ->where('code', config('restaurant.public_code'))
            ->where('is_active', true)
            ->firstOrFail();
        $categories = MenuCategory::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $menuItems = MenuItem::query()
            ->with([
                'category',
                'variants' => fn ($query) => $query->where('is_available', true),
                'addons' => fn ($query) => $query->where('is_available', true),
            ])
            ->where('is_available', true)
            ->where('restaurant_id', $restaurant->id)
            ->whereHas('category', fn ($query) => $query
                ->where('restaurant_id', $restaurant->id)
                ->where('is_active', true))
            ->orderBy('name')
            ->get();

        $branches = Branch::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('public.home', compact(
            'categories',
            'menuItems',
            'branches',
            'restaurant'
        ));
    }
}

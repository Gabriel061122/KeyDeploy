<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $activeProducts = Product::query()->where('active', true);

        return view('welcome', [
            'productCount' => (clone $activeProducts)->count(),
            'featured' => $activeProducts
                ->with(['provider', 'availabilities.region'])
                ->orderBy('name')
                ->limit(3)
                ->get(),
        ]);
    }
}

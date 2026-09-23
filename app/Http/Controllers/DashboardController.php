<?php

namespace App\Http\Controllers;

use App\Models\FurnitureProduct;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()?->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }
        $topProducts = FurnitureProduct::where('status', 'active')->withCount(['views', 'favorites', 'reservations'])->get()->sortByDesc(fn ($p) => $p->views_count + ($p->favorites_count * 3) + ($p->reservations_count * 5))->take(3)->values();

        return view('home', compact('topProducts'));
    }
}

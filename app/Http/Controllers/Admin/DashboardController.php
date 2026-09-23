<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FurnitureProduct;
use App\Models\Recommendation;
use App\Models\Reservation;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'customers' => User::where('role', 'customer')->count(),
            'products' => FurnitureProduct::count(),
            'pending_reservations' => Reservation::where('status', 'pending')->count(),
            'recommendations' => Recommendation::count(),
        ];
        $recentReservations = Reservation::with('user', 'product')->latest()->take(8)->get();
        $topProducts = FurnitureProduct::withCount(['views', 'favorites', 'reservations'])->get()->sortByDesc(fn ($p) => $p->views_count + ($p->favorites_count * 3) + ($p->reservations_count * 5))->take(5);

        return view('admin.dashboard', compact('stats', 'recentReservations', 'topProducts'));
    }
}

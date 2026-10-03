<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProductionReadiness;

class ProductionReadinessController extends Controller
{
    public function index(ProductionReadiness $readiness)
    {
        return view('admin.production-readiness', $readiness->inspect());
    }
}

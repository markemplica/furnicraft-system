<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrivacyAuditEvent;
use Illuminate\Http\Request;

class PrivacyAuditController extends Controller
{
    public function index(Request $request)
    {
        $events = PrivacyAuditEvent::with('user')->latest()->paginate(30)->withQueryString();

        return view('admin.privacy-audit', compact('events'));
    }
}

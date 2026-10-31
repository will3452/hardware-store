<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function stores(Request $request): View
    {
        return view('portals.stores', [
            'memberships' => $request->user()->memberships()->where('status', 'active')
                ->where('role', 'owner')
                ->whereHas('store', fn ($query) => $query->where('status', 'active'))
                ->with('store')->get(),
        ]);
    }
}

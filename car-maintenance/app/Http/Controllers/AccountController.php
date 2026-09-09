<?php

namespace App\Http\Controllers;

use App\Services\UserNameChanges;
use Illuminate\Http\Request;
use Inertia\Response;

class AccountController extends Controller
{
    public function show(Request $request, UserNameChanges $nameChanges): Response
    {
        return inertia('Account/Show', [
            'nameChangeQuota' => $nameChanges->quota($request->user()),
        ]);
    }
}

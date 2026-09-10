<?php

namespace App\Http\Controllers;

use App\Services\UserNameChanges;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Response;

class AccountController extends Controller
{
    public function show(Request $request, UserNameChanges $nameChanges): Response
    {
        $token = $request->query('token');
        $validToken = is_string($token) && Password::broker(config('fortify.passwords'))->tokenExists($request->user(), $token);

        return inertia('Account/Show', [
            'passwordResetToken' => $validToken ? $token : null,
            'passwordStatus' => $token && ! $validToken
                ? 'This verification link is invalid or expired. Please request a new email.'
                : $request->session()->get('passwordStatus'),
            'nameChangeQuota' => $nameChanges->quota($request->user()),
        ]);
    }
}

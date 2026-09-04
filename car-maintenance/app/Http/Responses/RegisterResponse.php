<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class RegisterResponse implements RegisterResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request): SymfonyResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', Response::HTTP_CREATED);
        }

        return redirect(config('fortify.home'))->with('success', 'Your account has been created successfully.');
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckJournalUser
{
    public function handle(Request $request, Closure $next)
    {

        $user = Auth::user();

        if (!$user || !$user->journalUser()->exists()) {
            return response()->json(['error' => 'Unauthorized: No approver assigned'], 403);
        }

        return $next($request);
    }
}

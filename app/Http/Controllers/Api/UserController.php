<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
//use Illuminate\Http\Request;

class UserController extends Controller
{
    public function userActivate($id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_approved' => 1]);

        return response()->json(['message' => 'Użytkownik aktywowany']);
    }

    public function userDeactivate($id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_approved' => 0]);

        return response()->json(['message' => 'Użytkownik dezaktywowany']);
    }
}

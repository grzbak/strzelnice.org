<?php

namespace App\Http\Controllers;

use App\Models\UserBan;
use Illuminate\Http\Request;

class UserBanController extends Controller
{
    /**
     * Lista banów
     */
    public function index()
    {
        return UserBan::with('user')->latest()->get();
    }

    /**
     * Dodanie bana
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'reason' => 'nullable|string'
        ]);

        $ban = UserBan::create([
            'user_id' => $data['user_id'],
            'reason' => $data['reason'] ?? null,
            'active' => true
        ]);

        return response()->json($ban, 201);
    }

    /**
     * Dezaktywacja bana
     */
    public function deactivate($id)
    {
        $ban = UserBan::findOrFail($id);
        $ban->update(['active' => false]);

        return response()->json(['message' => 'Ban dezaktywowany']);
    }

    /**
     * Usunięcie bana
     */
    public function destroy($id)
    {
        $ban = UserBan::findOrFail($id);
        $ban->delete();

        return response()->json(['message' => 'Ban usunięty']);
    }
}

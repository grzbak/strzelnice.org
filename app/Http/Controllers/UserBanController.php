<?php

namespace App\Http\Controllers;

use App\Models\UserBan;
use App\Services\UserSecurityService;
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
    public function store(Request $request, UserSecurityService $security)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'reason' => 'nullable|string',
        ]);

        $ban = UserBan::create([
            'user_id' => $data['user_id'],
            'reason' => $data['reason'] ?? null,
            'active' => true,
        ]);

        $security->recordBanForUser($ban->user, $request, $ban->reason);
        $security->audit('user.banned', $request->user(), $request, $ban->user, [
            'reason' => $ban->reason,
        ]);

        return response()->json($ban, 201);
    }

    /**
     * Dezaktywacja bana
     */
    public function deactivate(Request $request, UserSecurityService $security, $id)
    {
        $ban = UserBan::findOrFail($id);
        $ban->update(['active' => false]);

        $security->audit('user.ban_deactivated', $request->user(), $request, $ban->user);

        return response()->json(['message' => 'Ban dezaktywowany']);
    }

    /**
     * Usunięcie bana
     */
    public function destroy(Request $request, UserSecurityService $security, $id)
    {
        $ban = UserBan::findOrFail($id);
        $user = $ban->user;
        $ban->delete();

        $security->audit('user.ban_deleted', $request->user(), $request, $user);

        return response()->json(['message' => 'Ban usunięty']);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PrivacyAudit;
use App\Services\RoomPhotoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $user->update($data);

        return redirect()->route('profile.edit')->with('success', 'Your profile information has been updated.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update(['password' => $data['password']]);
        $request->session()->regenerate();

        return redirect()->route('profile.edit')->with('success', 'Your password has been changed.');
    }

    public function destroy(Request $request, RoomPhotoService $photos, PrivacyAudit $audit)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'confirm_account_deletion' => ['required', 'in:DELETE'],
        ]);
        $user = $request->user();
        $rooms = $user->roomImages()->get();
        foreach ($rooms as $room) $photos->deleteRoomFiles($room);
        DB::table('notification_logs')->where('user_id', $user->id)->delete();
        DB::table('password_reset_codes')->where('email', $user->email)->delete();
        $audit->record('customer_account_deleted', 'user', $user->id);
        DB::table('privacy_audit_events')->where('user_id', $user->id)->update(['subject_type' => null, 'subject_id' => null, 'metadata' => null]);
        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Your account and associated personal data were deleted. Reservation and recommendation records linked to the account were also removed.');
    }
}

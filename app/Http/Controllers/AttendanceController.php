<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(): View
    {
        return view('attendance.index', [
            'users' => User::query()->whereIn('role', ['staff', 'student'])->orderBy('role')->orderBy('name')->get(),
            'attendances' => Attendance::query()->with(['user', 'recorder'])->latest('time_in')->limit(100)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'action' => ['required', 'in:in,out'],
        ]);

        $user = User::query()->whereIn('role', ['staff', 'student'])->findOrFail($data['user_id']);
        $openAttendance = Attendance::query()
            ->where('user_id', $user->id)
            ->whereNull('time_out')
            ->latest('time_in')
            ->first();

        if ($data['action'] === 'in') {
            if ($openAttendance) {
                return back()->withErrors(['attendance' => $user->name . ' is already clocked in.']);
            }

            Attendance::create([
                'user_id' => $user->id,
                'recorded_by' => $request->user()->id,
                'time_in' => now(),
            ]);

            return back()->with('success', $user->name . ' was clocked in.');
        }

        if (! $openAttendance) {
            return back()->withErrors(['attendance' => $user->name . ' has no open clock-in.']);
        }

        $openAttendance->update(['time_out' => now()]);

        return back()->with('success', $user->name . ' was clocked out.');
    }
}
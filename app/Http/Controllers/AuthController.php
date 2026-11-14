<?php

namespace App\Http\Controllers;

use App\Models\CoeCode;
use App\Models\Student;
use App\Services\BillingService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin() { return view('auth.login'); }
    public function showActivate() { return view('auth.activate'); }
    public function showPassword() { return view('auth.password'); }

    public function login(Request $request)
    {
        $cred = $request->validate(['username' => 'required|string', 'password' => 'required|string']);

        if (! Auth::attempt(['username' => $cred['username'], 'password' => $cred['password'], 'is_active' => 1])) {
            return back()->withErrors(['username' => 'Wrong username or password, or the account is inactive.'])->onlyInput('username');
        }

        $user = Auth::user();
        if ($user->role === 'student' && ! optional($user->student)->is_activated) {
            Auth::logout();
            return back()->withErrors(['username' => 'Account not activated yet. Use "Activate account" with your COE code.']);
        }

        $request->session()->regenerate();
        Audit::log('login');

        return redirect()->route('home');
    }

    public function home()
    {
        return redirect()->route(match (auth()->user()->role) {
            'registrar' => 'registrar.dashboard',
            'cashier'   => 'cashier.dashboard',
            'teacher'   => 'teacher.home',
            default     => 'student.dashboard',
        });
    }

    public function logout(Request $request)
    {
        Audit::log('logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function activate(Request $request)
    {
        $data = $request->validate([
            'student_no' => 'required|string',
            'code'       => 'required|string',
            'password'   => 'required|min:8|confirmed',
        ]);

        $student = Student::where('student_no', trim($data['student_no']))->first();
        $coe = $student
            ? CoeCode::where('student_id', $student->id)
                ->where('code', strtoupper(trim($data['code'])))->whereNull('used_at')->first()
            : null;

        if (! $student || ! $coe || $student->is_activated) {
            throw ValidationException::withMessages(['code' => 'Invalid student number or COE code, or the account is already activated.']);
        }

        DB::transaction(function () use ($student, $coe, $data) {
            $student->user->update(['password' => $data['password']]);
            $student->update(['is_activated' => true]);
            $coe->update(['used_at' => now()]);
            app(BillingService::class)->openTerm($student, $coe->schoolTerm, $student->year_level);
        });

        return redirect()->route('login')->with('status', 'Account activated. You can now log in.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'password'         => 'required|min:8|confirmed',
        ]);

        $request->user()->update(['password' => $data['password']]);
        Audit::log('password.change');

        return redirect()->route('home')->with('status', 'Password changed.');
    }

    public function notifications()
    {
        $q = DB::table('user_notifications')->where('user_id', auth()->id());

        return response()->json([
            'unread' => (clone $q)->where('is_read', false)->count(),
            'items'  => $q->orderByDesc('id')->limit(8)->get(['message', 'is_read', 'created_at']),
        ]);
    }

    public function markRead()
    {
        DB::table('user_notifications')->where('user_id', auth()->id())->update(['is_read' => true]);
        return response()->noContent();
    }
}
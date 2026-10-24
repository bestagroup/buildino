<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Web\ManagementDashboardAccessService;
use App\Services\Web\PortalAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class InitialPasswordChangeController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->is_active && ! $user->is_blocked, 403);

        if (! $user->must_change_password) {
            return $this->dashboard();
        }

        return view('auth.change-initial-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->is_active && ! $user->is_blocked, 403);

        if (! $user->must_change_password) {
            return $this->dashboard();
        }

        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => [
                'required',
                'confirmed',
                Password::min(10)->letters()->numbers(),
            ],
        ]);

        if (Hash::check($data['password'], $user->password)
            || hash_equals('123456789', $data['password'])) {
            throw ValidationException::withMessages([
                'password' => 'رمز جدید نباید با رمز اولیه یا رمز فعلی یکسان باشد.',
            ]);
        }

        DB::transaction(function () use ($user, $data): void {
            $user->forceFill([
                'password' => $data['password'],
                'must_change_password' => false,
                'remember_token' => Str::random(60),
            ])->save();

            // Any previously issued mobile API tokens must not remain valid.
            $user->tokens()->delete();
        });

        $request->session()->regenerate();

        return $this->dashboard()->with(
            'status',
            'رمز عبور حساب با موفقیت تغییر کرد.'
        );
    }

    private function dashboard(): RedirectResponse
    {
        $user = auth('web')->user();

        if (app(ManagementDashboardAccessService::class)->hasAnyAccess($user)) {
            return redirect()->route('management.dashboard');
        }

        if (app(PortalAccessService::class)->hasAnyAccess($user)) {
            return redirect()->route('portal.dashboard');
        }

        return redirect()->route('portal.login');
    }
}

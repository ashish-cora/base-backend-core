<!-- CHILD: override with your own two-factor settings screen -->
<h1>Two-factor authentication (base stub)</h1>
<p><a href="{{ route('home') }}">Back to home</a></p>
@if (session('status'))
    <p>{{ session('status') }}</p>
@endif
@error('code', 'confirmTwoFactorAuthentication')
    <p>{{ $message }}</p>
@enderror
@php $twoFactorUser = auth()->user(); @endphp
@if (is_null($twoFactorUser->two_factor_secret))
    {{-- STATE A: disabled — Enable only, never Disable --}}
    <p>Two-factor is disabled.</p>
    <form method="POST" action="{{ route('two-factor.enable') }}">
        @csrf
        <button type="submit">Enable two-factor</button>
    </form>
    <p>If prompted, <a href="{{ route('password.confirm') }}">confirm your password first</a>, then come back and enable.</p>
@elseif (is_null($twoFactorUser->two_factor_confirmed_at))
    {{-- STATE B: confirming — QR + Confirm + Cancel, never Enable --}}
    <p>Scan this QR code with your authenticator app, then confirm with a code.</p>
    <div>{!! $twoFactorUser->twoFactorQrCodeSvg() !!}</div>
    <p>Manual entry not working? Fetch the setup key from <code>{{ route('two-factor.secret-key') }}</code> (JSON <code>secretKey</code>) and enter it manually.</p>
    <form method="POST" action="{{ route('two-factor.confirm') }}">
        @csrf
        <label>Code <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required></label>
        <button type="submit">Confirm</button>
    </form>
    <form method="POST" action="{{ route('two-factor.disable') }}">
        @csrf
        @method('DELETE')
        <button type="submit">Cancel setup</button>
    </form>
@else
    {{-- STATE C: enabled — Disable only, never Enable --}}
    <p>Two-factor is enabled.</p>
    <div>{!! $twoFactorUser->twoFactorQrCodeSvg() !!}</div>
    <p>Keep your recovery codes safe. You can view them at <a href="{{ route('two-factor.recovery-codes') }}">recovery codes</a>.</p>
    <form method="POST" action="{{ route('two-factor.disable') }}">
        @csrf
        @method('DELETE')
        <button type="submit">Disable two-factor</button>
    </form>
@endif
<p>Note: enabling, confirming and disabling require recent password confirmation. If redirected, confirm at <a href="{{ route('password.confirm') }}">confirm password</a>.</p>

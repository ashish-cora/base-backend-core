<!-- CHILD: override with your own reset-password screen -->
<h1>Reset password (base stub)</h1>
<form method="POST" action="{{ route('password.update') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token ?? request('token') }}">
    <label>Email <input type="email" name="email" value="{{ request('email') }}" required></label>
    <label>Password <input type="password" name="password" required></label>
    <label>Confirm <input type="password" name="password_confirmation" required></label>
    <button type="submit">Reset</button>
</form>

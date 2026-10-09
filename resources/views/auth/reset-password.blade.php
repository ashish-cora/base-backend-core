<!-- CHILD: override with your own reset-password screen -->
<h1>Reset password (base stub)</h1>
@if ($errors->any())
    <ul>
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif
<form method="POST" action="{{ route('password.update') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token ?? request('token') }}">
    <label>Email <input type="email" name="email" value="{{ old('email', request('email')) }}" required></label>
    <label>Password <input type="password" name="password" required></label>
    <label>Confirm <input type="password" name="password_confirmation" required></label>
    <button type="submit">Reset</button>
</form>

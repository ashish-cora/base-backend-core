<!-- CHILD: override with your own change-password screen -->
<h1>Change password (base stub)</h1>
<p><a href="{{ route('home') }}">Back to home</a></p>
@if (session('status'))
    <p>{{ session('status') }}</p>
@endif
<form method="POST" action="{{ route('user-password.update') }}">
    @csrf
    @method('PUT')
    <label>Current password <input type="password" name="current_password" required></label>
    @error('current_password', 'updatePassword')
        <p>{{ $message }}</p>
    @enderror
    <label>New password <input type="password" name="password" required></label>
    @error('password', 'updatePassword')
        <p>{{ $message }}</p>
    @enderror
    <label>Confirm new password <input type="password" name="password_confirmation" required></label>
    <button type="submit">Update password</button>
</form>

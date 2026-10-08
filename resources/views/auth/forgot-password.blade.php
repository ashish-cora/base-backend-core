<!-- CHILD: override with your own forgot-password screen -->
<h1>Forgot password (base stub)</h1>
<form method="POST" action="{{ route('password.email') }}">
    @csrf
    <label>Email <input type="email" name="email" required></label>
    <button type="submit">Send reset link</button>
</form>

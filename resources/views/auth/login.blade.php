<!-- CHILD: override with your own login screen -->
<h1>Login (base stub)</h1>
<form method="POST" action="{{ route('login.store') }}">
    @csrf
    <label>Email <input type="email" name="email" required></label>
    <label>Password <input type="password" name="password" required></label>
    <label><input type="checkbox" name="remember" value="1"> Remember me</label>
    <button type="submit">Log in</button>
</form>

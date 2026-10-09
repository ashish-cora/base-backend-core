<!-- CHILD: override with your own dashboard screen -->
<h1>Home (base stub)</h1>
<p>Logged in as {{ auth()->user()->email }}</p>
<nav>
    <a href="{{ route('profile.password') }}">Change password</a>
    <a href="{{ route('profile.two-factor') }}">Two-factor settings</a>
</nav>
<form method="POST" action="{{ route('logout') }}">
    @csrf
    <button type="submit">Log out</button>
</form>

<!-- CHILD: override with your own confirm-password screen -->
<h1>Confirm password (base stub)</h1>
<form method="POST" action="{{ route('password.confirm.store') }}">
    @csrf
    <label>Password <input type="password" name="password" required></label>
    <button type="submit">Confirm</button>
</form>

<!-- CHILD: override with your own 2FA challenge screen -->
<h1>Two-factor challenge (base stub)</h1>
<form method="POST" action="{{ route('two-factor.login.store') }}">
    @csrf
    <label>Code <input type="text" name="code" inputmode="numeric"></label>
    <label>Recovery code <input type="text" name="recovery_code"></label>
    <button type="submit">Verify</button>
</form>

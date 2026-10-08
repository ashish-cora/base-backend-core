<!-- CHILD: override with your own verify-email screen -->
<h1>Verify email (base stub)</h1>
<form method="POST" action="{{ route('verification.send') }}">
    @csrf
    <button type="submit">Resend verification email</button>
</form>

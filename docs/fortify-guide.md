# Fortify — How We Use It + How to Change It

> Rule: never edit `vendor/laravel/fortify`. It is the engine. We steer it from `app/` + `config/`.

## 1. The idea in 30 seconds

* **Vendor** already knows login, logout, password reset, email verify, 2FA.
* **Our project** only does 2 things:
  1. Tells it what to turn on via `config/fortify.php`
  2. Plugs in our user rules via `FortifyServiceProvider` + `app/Actions/Fortify`

## 2. Where things live

| What | Where |
|---|---|
| Engine (don't touch) | `vendor/laravel/fortify` |
| On/off switches | `config/fortify.php` |
| Who can login + wiring | `app/Providers/FortifyServiceProvider.php` |
| Password / profile rules | `app/Actions/Fortify/` |
| User model | `app/Models/User.php` |

## 3. How to change logic without touching vendor

### Login — change who can log in
Edit `Fortify::authenticateUsing()` in `FortifyServiceProvider`.
Return `User` or `null`.

```php
Fortify::authenticateUsing(function (Request $request) {
    // add your checks here: status, verified email, role, tenant...
    return $user; // or null to reject
});
```

Need full control (captcha, custom steps)? Use:
```php
Fortify::authenticateThrough(fn () => [
    // your own pipeline steps
]);
```

### Password / profile — change what happens
Edit files in `app/Actions/Fortify/`:
* `UpdateUserPassword.php`, `ResetUserPassword.php` → password rules + hashing
* `UpdateUserProfileInformation.php` → name/email validation
* Need registration? Create `CreateNewUser.php` + `Fortify::createUsersUsing()`

Vendor controllers call these automatically.

### Redirects / URLs — change where it goes
`config/fortify.php`: `home`, `redirects.*`, `paths.*`, `prefix`.

Custom success response (e.g. JSON after login):
bind your class to `LoginResponse`, `LogoutResponse`, `TwoFactorLoginResponse` contracts. Vendor resolves via `app(XResponse::class)`, so yours wins.

### Logout / side-effects
Don't replace the controller. Listen for events in provider:

```php
Event::listen(Login::class, fn () => ...);
Event::listen(Logout::class, fn () => ...);
```
Use for cleanup, audit, logging.

### Replace everything (rare)
```php
Fortify::ignoreRoutes();
// + define your own routes
```

## 4. Cheat sheet

* `config` = which features / URLs / limits
* `FortifyServiceProvider` = who + how
* `app/Actions` = domain rules
* `Events/Listeners` = side-effects

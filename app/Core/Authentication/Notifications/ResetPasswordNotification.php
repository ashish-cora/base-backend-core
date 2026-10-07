<?php

namespace App\Core\Authentication\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;

/**
 * Web-core reset notification: builds a browser URL instead of
 * route('password.reset'), which viewless Fortify (views=false) never defines.
 * Token-API reset links are owned by the dedicated API repository.
 */
class ResetPasswordNotification extends BaseResetPassword
{
    protected function resetUrl(mixed $notifiable): string
    {
        $email = is_array($notifiable) ? ($notifiable['email'] ?? '') : ($notifiable->email ?? '');
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return $base.'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $email,
        ]);
    }
}

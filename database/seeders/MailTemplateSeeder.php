<?php

namespace Database\Seeders;

use App\Models\MailTemplate;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class MailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Default Layout
        if (! Setting::getValue('mail_layout')) {
            Setting::setValue('mail_layout', '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 40px 20px; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #999; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        {content}
        <div class="footer">
            &copy; {year} {site_name}. All rights reserved.
        </div>
    </div>
</body>
</html>', 'string', 'mail');
        }

        $templates = [
            [
                'slug' => 'email_verify_otp',
                'name' => 'Email Verification OTP',
                'subject' => '{otp_code} is your verification code',
                'content' => '<h1>Verify Your Email</h1><p>Hi {user_name},</p><p>Your verification code is: <strong>{otp_code}</strong></p><p>Thanks, {site_name}</p>',
                'category' => 'auth',
            ],
            [
                'slug' => 'welcome_email',
                'name' => 'Welcome Email',
                'subject' => 'Welcome to {site_name}!',
                'content' => '<h1>Welcome aboard!</h1><p>Hi {user_name},</p><p>We are excited to have you with us. Start creating amazing content today!</p><p><a href="{site_url}">Go to Dashboard</a></p>',
                'category' => 'auth',
            ],
            [
                'slug' => 'subscription_success',
                'name' => 'Subscription Success',
                'subject' => 'Your subscription is now active!',
                'content' => '<h1>Subscription Confirmed</h1><p>Hi {user_name},</p><p>Your {plan_name} plan is now active. Enjoy your unlimited credits!</p>',
                'category' => 'subscription',
            ],
            [
                'slug' => 'newsletter_optin',
                'name' => 'Newsletter Opt-in',
                'subject' => 'Please confirm your subscription',
                'content' => '<h1>Confirm Subscription</h1><p>Click below to join our newsletter and get latest AI updates.</p><p><a href="{site_url}/newsletter/confirm">Confirm Now</a></p>',
                'category' => 'newsletter',
            ],
            [
                'slug' => 'password_changed',
                'name' => 'Password Changed',
                'subject' => 'Security Alert: Your password has been changed',
                'content' => '<h1>Security Notification</h1><p>Hi {user_name},</p><p>This is a confirmation that your password has been successfully changed. If you did not perform this action, please contact support immediately.</p>',
                'category' => 'account',
            ],
            [
                'slug' => 'password_reset_otp',
                'name' => 'Password Reset OTP',
                'subject' => '{otp_code} is your password reset code',
                'content' => '<h1>Reset Your Password</h1><p>Hi {user_name},</p><p>You requested a password reset. Use the code below to continue:</p><h2>{otp_code}</h2><p>This code will expire in 10 minutes.</p>',
                'category' => 'auth',
            ],
        ];

        foreach ($templates as $t) {
            MailTemplate::updateOrCreate(['slug' => $t['slug']], $t);
        }
    }
}

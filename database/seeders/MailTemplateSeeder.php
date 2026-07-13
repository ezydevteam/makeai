<?php

namespace Database\Seeders;

use App\Models\MailTemplate;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class MailTemplateSeeder extends Seeder
{
    public const SYSTEM_TEMPLATE_SLUGS = [
        'email_verify_otp',
        'reset_password_otp',
        'login_otp',
        'welcome',
        'password_changed',
        'email_changed',
        'account_suspended',
        'account_activated',
        'credits_added',
        'credits_low',
        'referral_earned',
        'admin_announcement',
        'subscription_started',
        'subscription_renewed',
        'subscription_expiring_soon',
        'subscription_expired',
        'subscription_canceled',
        'subscription_ended',
        'subscription_upgraded',
        'subscription_downgraded',
        'subscription_payment_failed',
        'subscription_trial_started',
        'subscription_trial_expiring',
        'subscription_trial_ended',
        'invoice_paid',
        'newsletter_confirm',
        'newsletter_unsubscribed',
        'newsletter_campaign',
        'affiliate_commission_earned',
        'affiliate_commission_rejected',
        'affiliate_payout_paid',
        'affiliate_payout_rejected',
    ];

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
                'requires_pro' => false,
            ],
            [
                'slug' => 'reset_password_otp',
                'name' => 'Reset Password OTP',
                'subject' => '{otp_code} is your password reset code',
                'content' => '<h1>Reset Your Password</h1><p>Hi {user_name},</p><p>You requested a password reset. Use the code below to continue:</p><h2>{otp_code}</h2><p>This code will expire soon.</p>',
                'category' => 'auth',
                'requires_pro' => false,
            ],
            [
                'slug' => 'login_otp',
                'name' => 'Login OTP (2FA via email)',
                'subject' => '{otp_code} is your login code',
                'content' => '<h1>Login Verification</h1><p>Hi {user_name},</p><p>Use this code to complete your login:</p><h2>{otp_code}</h2><p>If this was not you, please secure your account.</p>',
                'category' => 'auth',
                'requires_pro' => false,
            ],
            [
                'slug' => 'welcome',
                'name' => 'Welcome / New Account Created',
                'subject' => 'Welcome to {site_name}!',
                'content' => '<h1>Welcome aboard!</h1><p>Hi {user_name},</p><p>We are excited to have you with us. Start creating amazing content today!</p><p><a href="{site_url}">Go to Dashboard</a></p>',
                'category' => 'account',
                'requires_pro' => false,
            ],
            [
                'slug' => 'password_changed',
                'name' => 'Password Changed Notification',
                'subject' => 'Security Alert: Your password has been changed',
                'content' => '<h1>Security Notification</h1><p>Hi {user_name},</p><p>This is a confirmation that your password has been successfully changed. If you did not perform this action, please contact support immediately.</p>',
                'category' => 'account',
                'requires_pro' => false,
            ],
            [
                'slug' => 'email_changed',
                'name' => 'Email Address Changed',
                'subject' => 'Your email address was changed',
                'content' => '<h1>Email Changed</h1><p>Hi {user_name},</p><p>Your account email address was changed to {user_email}.</p><p>If you did not request this change, please contact support.</p>',
                'category' => 'account',
                'requires_pro' => false,
            ],
            [
                'slug' => 'account_suspended',
                'name' => 'Account Suspended',
                'subject' => 'Your account has been suspended',
                'content' => '<h1>Account Suspended</h1><p>Hi {user_name},</p><p>Your account has been suspended. Please contact support if you believe this is a mistake.</p>',
                'category' => 'account',
                'requires_pro' => false,
            ],
            [
                'slug' => 'account_activated',
                'name' => 'Account Activated',
                'subject' => 'Your account is active again',
                'content' => '<h1>Account Activated</h1><p>Hi {user_name},</p><p>Your account has been activated. You can sign in and continue using {site_name}.</p>',
                'category' => 'account',
                'requires_pro' => false,
            ],
            [
                'slug' => 'credits_added',
                'name' => 'Credits Added to Account',
                'subject' => 'Credits added to your account',
                'content' => '<h1>Credits Added</h1><p>Hi {user_name},</p><p>{credits} credits were added to your account.</p>',
                'category' => 'account',
                'requires_pro' => false,
            ],
            [
                'slug' => 'credits_low',
                'name' => 'Low Credit Balance Warning',
                'subject' => 'Your credit balance is low',
                'content' => '<h1>Low Credit Balance</h1><p>Hi {user_name},</p><p>Your current credit balance is {credits}. Add more credits to keep generating without interruption.</p>',
                'category' => 'account',
                'requires_pro' => false,
            ],
            [
                'slug' => 'referral_earned',
                'name' => 'Referral Commission Earned',
                'subject' => 'You earned a referral reward',
                'content' => '<h1>Referral Reward Earned</h1><p>Hi {user_name},</p><p>Your referral reward has been credited to your account.</p>',
                'category' => 'account',
                'requires_pro' => false,
            ],
            [
                'slug' => 'admin_announcement',
                'name' => 'Admin Announcement / Broadcast',
                'subject' => 'Announcement from {site_name}',
                'content' => '<h1>Announcement</h1><p>Hi {user_name},</p><p>{message}</p>',
                'category' => 'account',
                'requires_pro' => false,
            ],
            [
                'slug' => 'subscription_started',
                'name' => 'Subscription Started / Welcome to Plan',
                'subject' => 'Welcome to {plan_name}',
                'content' => '<h1>Subscription Started</h1><p>Hi {user_name},</p><p>Your {plan_name} subscription is now active.</p>',
                'category' => 'subscription',
                'requires_pro' => true,
            ],
            [
                'slug' => 'subscription_renewed',
                'name' => 'Subscription Renewed Successfully',
                'subject' => 'Your subscription was renewed',
                'content' => '<h1>Subscription Renewed</h1><p>Hi {user_name},</p><p>Your {plan_name} subscription has been renewed successfully.</p>',
                'category' => 'subscription',
                'requires_pro' => true,
            ],
            [
                'slug' => 'subscription_expiring_soon',
                'name' => 'Subscription Expiring Soon (3 days)',
                'subject' => 'Your subscription expires soon',
                'content' => '<h1>Subscription Expiring Soon</h1><p>Hi {user_name},</p><p>Your {plan_name} subscription expires soon. Renew now to avoid interruption.</p>',
                'category' => 'subscription',
                'requires_pro' => true,
            ],
            [
                'slug' => 'subscription_expired',
                'name' => 'Subscription Expired',
                'subject' => 'Your subscription has expired',
                'content' => '<h1>Subscription Expired</h1><p>Hi {user_name},</p><p>Your {plan_name} subscription has expired. Upgrade to continue using Pro features.</p>',
                'category' => 'subscription',
                'requires_pro' => true,
            ],
            [
                'slug' => 'subscription_canceled',
                'name' => 'Subscription Canceled',
                'subject' => 'Your subscription was canceled',
                'content' => '<h1>Subscription Canceled</h1><p>Hi {user_name},</p><p>Your {plan_name} subscription has been canceled.</p>',
                'category' => 'subscription',
                'requires_pro' => true,
            ],
            [
                'slug' => 'subscription_ended',
                'name' => 'Subscription Ended by Administrator',
                'subject' => 'Your subscription has been ended',
                'content' => '<h1>Subscription Ended</h1><p>Hi {user_name},</p><p>Your {plan_name} subscription was ended by an administrator and your access has been removed. {reason}</p><p>If you have any questions, please contact our support team.</p>',
                'category' => 'subscription',
                'requires_pro' => true,
            ],
            [
                'slug' => 'subscription_upgraded',
                'name' => 'Plan Upgraded',
                'subject' => 'Your plan was upgraded',
                'content' => '<h1>Plan Upgraded</h1><p>Hi {user_name},</p><p>Your subscription has been upgraded to {plan_name}.</p>',
                'category' => 'subscription',
                'requires_pro' => true,
            ],
            [
                'slug' => 'subscription_downgraded',
                'name' => 'Plan Downgraded',
                'subject' => 'Your plan was downgraded',
                'content' => '<h1>Plan Downgraded</h1><p>Hi {user_name},</p><p>Your subscription has been changed to {plan_name}.</p>',
                'category' => 'subscription',
                'requires_pro' => true,
            ],
            [
                'slug' => 'subscription_payment_failed',
                'name' => 'Payment Failed / Retry Notice',
                'subject' => 'Payment failed for your subscription',
                'content' => '<h1>Payment Failed</h1><p>Hi {user_name},</p><p>We could not process your payment for {plan_name}. Please update your billing details.</p>',
                'category' => 'subscription',
                'requires_pro' => true,
            ],
            [
                'slug' => 'subscription_trial_started',
                'name' => 'Trial Started',
                'subject' => 'Your trial has started',
                'content' => '<h1>Trial Started</h1><p>Hi {user_name},</p><p>Your {plan_name} trial has started. Enjoy exploring Pro features.</p>',
                'category' => 'subscription',
                'requires_pro' => true,
            ],
            [
                'slug' => 'subscription_trial_expiring',
                'name' => 'Trial Expiring Soon (1 day)',
                'subject' => 'Your trial expires soon',
                'content' => '<h1>Trial Expiring Soon</h1><p>Hi {user_name},</p><p>Your {plan_name} trial expires soon. Upgrade now to keep access.</p>',
                'category' => 'subscription',
                'requires_pro' => true,
            ],
            [
                'slug' => 'subscription_trial_ended',
                'name' => 'Trial Ended - Upgrade Now',
                'subject' => 'Your trial has ended',
                'content' => '<h1>Trial Ended</h1><p>Hi {user_name},</p><p>Your trial has ended. Upgrade to continue using Pro features.</p>',
                'category' => 'subscription',
                'requires_pro' => true,
            ],
            [
                'slug' => 'invoice_paid',
                'name' => 'Invoice / Receipt',
                'subject' => 'Receipt for your payment',
                'content' => '<h1>Payment Receipt</h1><p>Hi {user_name},</p><p>Thank you for your payment for {plan_name}. Your invoice is now paid.</p>',
                'category' => 'subscription',
                'requires_pro' => true,
            ],
            [
                'slug' => 'newsletter_confirm',
                'name' => 'Newsletter Subscription Confirmation (double opt-in)',
                'subject' => 'Please confirm your subscription',
                'content' => '<h1>Confirm Subscription</h1><p>Click below to join our newsletter and get the latest updates.</p><p><a href="{confirm_url}">Confirm Now</a></p>',
                'category' => 'newsletter',
                'requires_pro' => false,
            ],
            [
                'slug' => 'newsletter_unsubscribed',
                'name' => 'Unsubscribe Confirmation',
                'subject' => 'You have been unsubscribed',
                'content' => '<h1>Unsubscribed</h1><p>You have been removed from the {site_name} newsletter list.</p>',
                'category' => 'newsletter',
                'requires_pro' => false,
            ],
            [
                'slug' => 'newsletter_campaign',
                'name' => 'Newsletter Campaign (base template)',
                'subject' => '{campaign_subject}',
                'content' => '<h1>{campaign_title}</h1><p>{campaign_content}</p><p><a href="{unsubscribe_url}">Unsubscribe</a></p>',
                'category' => 'newsletter',
                'requires_pro' => false,
            ],
            [
                'slug' => 'affiliate_commission_earned',
                'name' => 'Affiliate — Commission Earned',
                'subject' => 'You earned a commission of {amount}',
                'content' => '<h1>Commission Earned 🎉</h1><p>Hi {user_name},</p><p>Great news — you just earned a commission of <strong>{amount}</strong> on {site_name}.</p><p><a href="{site_url}">View your affiliate dashboard</a></p>',
                'category' => 'affiliate',
                'requires_pro' => false,
            ],
            [
                'slug' => 'affiliate_commission_rejected',
                'name' => 'Affiliate — Commission Rejected',
                'subject' => 'A commission of {amount} was reversed',
                'content' => '<h1>Commission Reversed</h1><p>Hi {user_name},</p><p>A commission of <strong>{amount}</strong> on {site_name} has been reversed (for example, the referred purchase was refunded or did not qualify).</p><p><a href="{site_url}">View your affiliate dashboard</a></p>',
                'category' => 'affiliate',
                'requires_pro' => false,
            ],
            [
                'slug' => 'affiliate_payout_paid',
                'name' => 'Affiliate — Payout Sent',
                'subject' => 'Your payout of {amount} has been sent',
                'content' => '<h1>Payout Sent 💸</h1><p>Hi {user_name},</p><p>Your affiliate payout of <strong>{amount}</strong> has been sent via {method}.</p><p><a href="{site_url}">View your affiliate dashboard</a></p>',
                'category' => 'affiliate',
                'requires_pro' => false,
            ],
            [
                'slug' => 'affiliate_payout_rejected',
                'name' => 'Affiliate — Payout Rejected',
                'subject' => 'Your payout request of {amount} was declined',
                'content' => '<h1>Payout Request Declined</h1><p>Hi {user_name},</p><p>Your affiliate payout request of <strong>{amount}</strong> on {site_name} was declined. Please review your payout details or contact support.</p><p><a href="{site_url}">View your affiliate dashboard</a></p>',
                'category' => 'affiliate',
                'requires_pro' => false,
            ],
        ];

        foreach ($templates as $t) {
            MailTemplate::firstOrCreate(['slug' => $t['slug']], array_merge(['is_system' => true], $t));
        }
    }
}

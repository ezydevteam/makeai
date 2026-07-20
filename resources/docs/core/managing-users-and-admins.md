---
title: Managing Users and Admins
slug: managing-users-and-admins
page: managing-users-and-admins.html
section: Roles
license: regular
keywords: [users, customers, admins, staff, add admin, edit user, ban, suspend, impersonate, login as user, delete user, credits, plan, difference between users and admins, user vs admin, customer account vs staff account]
---

**Admin → Roles** splits your team from your customers into two separate lists: **Users** are the people who sign up and use your AI tools; **Admins** are the staff accounts that can log into this admin panel. They are entirely separate account types — a customer can never accidentally get admin access, and vice versa.

## Viewing and managing your customer list

**Admin → Roles → Users** lists every customer account, with stat cards for Total Users, New Users (last 7 days), Active Users (last 7 days), and Banned Users. Each row shows the customer's name/email/country, their credit balance, their plan, and an active/inactive status badge.

From the row's action menu you can **Edit User** to open their full profile, **Login as User** to see your site exactly as they do, or **Delete** to move them to Trash (recoverable) rather than deleting immediately. Selecting several rows with the checkboxes opens a bulk-action bar for activating, deactivating, adding credits to, or deleting multiple accounts at once.

## Editing a single customer's account

Opening a user (Edit User, or clicking their row) lets you change their name, email, credit balance, plan, country, profession, and password, plus **activate/deactivate** the account, **ban** them (with a required reason), **force-disable their two-factor authentication** if they've locked themselves out, or send them a notification. A permanently deleted user (via Trash → force delete) can only be done by a Super Admin.

## Adding or editing a staff (admin) account

**Admin → Roles → Admins** lists everyone who can sign in to this admin panel, with their assigned role, active/inactive status, and last login. **Add Admin** opens a form for name, email, password, and a role — the role determines exactly what that person can see and do (see the next section). Editing an existing admin works the same way; leave the password field blank to keep their current one.

## Why you can't edit or delete a certain admin

- You can't demote, deactivate, or delete an account with the **Super Admin** role unless you are a Super Admin yourself — this is enforced even from the edit screen.
- The system will refuse to demote or deactivate the **last remaining active Super Admin**, so your site can never end up with no one able to manage it.
- A Super Admin can never remove their own Super Admin role or deactivate their own account, even by mistake.
- If an admin has two-factor authentication turned on and you're locked out of their account, a Super Admin can force-disable 2FA for them from the edit screen — look for the highlighted panel with a **Disable 2FA** button.

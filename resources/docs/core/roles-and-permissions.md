---
title: Roles and Permissions
slug: roles-and-permissions
page: roles-and-permissions.html
section: Roles
license: regular
keywords: [rbac, roles, permissions, custom role, super admin, manager, support role, content manager, access control, staff permissions, cant see menu, missing permission, restore default role, create role, assign role, cant delete role]
---

**Admin → Roles → Admins → Manage Roles** controls exactly what each staff account can see and do. Every admin is assigned exactly one role, and that role's permission checklist decides which sidebar sections and actions they have access to.

## The default roles

Your site starts with four roles: **Super Admin** (full, unrestricted access to everything — cannot be edited or deleted), **Manager**, **Support**, and **Content Manager**. The three non-Super roles each start with a sensible default set of permissions for their name, but every checkbox on them can be changed.

## Creating a custom role

Click **Create Role**, give it a name and description, then tick the permissions it should have from the grid — permissions are grouped by area (dashboard, users, admins, roles, settings, AI, content, plans, payments, addons, themes, translations, reports, support, contact, marketing, system). Use **Select All** or **Deselect All** to speed this up, then assign the role to an admin from **Admin → Roles → Admins**.

## Editing permissions or restoring defaults

Open any non-Super role and toggle individual permissions on or off. If you've changed a default role (Manager, Support, or Content Manager) and want to undo your changes, use **Restore Default** to reset it back to its original permission set in one click.

## Why you can't edit or delete a role

- **Super Admin** can never be edited, renamed, or deleted — it always has full access, by design, and its permission grid is shown for reference only (every box appears checked and locked).
- A role that's currently assigned to one or more admins **cannot be deleted** — reassign those admins to a different role first, then delete it.
- If you're editing a role as a non-Super-Admin, you can't grant permissions you don't personally hold, and certain sensitive permissions (managing other admins, managing roles, impersonating users, or verifying the license) can only be granted by a Super Admin.
- If a teammate reports they "can't see" a menu item or button that this guide describes, it's almost always a missing permission on their role — open their role here and check the relevant category.

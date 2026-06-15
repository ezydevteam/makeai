# Immediate Fix — ezydev.net live install

## Issue 3: rate_limit_hits missing unique constraint

Run on your server (adjust the PHP 8.3 binary path as established earlier):

```bash
cd ~/public_html/makeai-main

# 1. Create a new migration
/opt/cpanel/ea-php83/root/usr/bin/php artisan make:migration add_unique_to_rate_limit_hits_table
```

Edit the new file in `database/migrations/`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rate_limit_hits', function (Blueprint $table) {
            $table->unique(['key', 'window_start']);
        });
    }

    public function down(): void
    {
        Schema::table('rate_limit_hits', function (Blueprint $table) {
            $table->dropUnique(['rate_limit_hits_key_window_start_unique']);
        });
    }
};
```

Then run:

```bash
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --force
```

## Also fix the original migration file for future fresh installs

Edit `database/migrations/2026_06_10_000001_create_rate_limit_hits_table.php` and add
`$table->unique(['key', 'window_start']);` inside the `Schema::create` closure, so a
`migrate:fresh` from scratch also has the constraint (avoids needing two migrations
on the next clean deploy).

## Issue 2: Installation wizard

Once both fixes above are applied and the site loads correctly, re-test `/install`.
If it still fails, run:

```bash
tail -80 ~/public_html/makeai-main/storage/logs/laravel.log
```

and share the output — most likely related to the same `rate_limit_hits` upsert
firing during a wizard step (license check / admin creation triggers auth-category
rate limit hits).

<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\BlogPost;

class BlogPostPolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->hasPermission('content.blog');
    }

    public function create(Admin $admin): bool
    {
        return $admin->hasPermission('content.blog');
    }

    // $post is optional so this ability can be checked at the class level too
    // (e.g. bulk actions authorize 'update' against BlogPost::class, with no
    // single instance to pass). The permission check doesn't need the model.
    public function update(Admin $admin, ?BlogPost $post = null): bool
    {
        return $admin->hasPermission('content.blog');
    }

    public function delete(Admin $admin, BlogPost $post): bool
    {
        return $admin->hasPermission('content.blog');
    }

    public function restore(Admin $admin, BlogPost $post): bool
    {
        return $admin->hasPermission('content.blog');
    }

    public function forceDelete(Admin $admin, BlogPost $post): bool
    {
        return $admin->hasPermission('content.blog');
    }

    public function preview(Admin $admin, BlogPost $post): bool
    {
        return $admin->hasPermission('content.blog');
    }
}

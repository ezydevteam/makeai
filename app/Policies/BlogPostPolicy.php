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

    public function update(Admin $admin, BlogPost $post): bool
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

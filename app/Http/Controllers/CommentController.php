<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    /**
     * Post a comment.
     */
    public function store(Request $request)
    {
        $request->validate([
            'commentable_type' => 'required|string',
            'commentable_id' => 'required|integer',
            'content' => 'required|string|max:1000',
            'parent_id' => 'nullable|exists:comments,id',
        ]);

        $comment = Comment::create([
            'commentable_type' => $request->commentable_type,
            'commentable_id' => $request->commentable_id,
            'user_id' => Auth::id(),
            'parent_id' => $request->parent_id,
            'content' => $request->content,
            'status' => settings('auto_approve_comments', false) ? 'approved' : 'pending',
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', $comment->status === 'approved' ? 'Comment posted!' : 'Comment submitted for approval.');
    }

    /**
     * Like a comment.
     */
    public function like(Comment $comment)
    {
        $comment->increment('likes_count');

        return back();
    }
}

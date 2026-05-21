<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ForumController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('forum_posts');
        
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        
        $sort = $request->get('sort', 'hot');
        if ($sort === 'new') {
            $query->orderByDesc('created_at');
        } elseif ($sort === 'top') {
            $query->orderByDesc('upvotes');
        } else {
            $query->orderByDesc('is_pinned')
                  ->orderByRaw('(upvotes - downvotes + reply_count * 2) DESC');
        }
        
        $posts = $query->limit(50)->get()->map(fn($p) => (array)$p)->toArray();
        
        return view('forum.index', ['posts' => $posts]);
    }
    
    public function show($id)
    {
        $post = DB::table('forum_posts')->where('id', $id)->first();
        if (!$post) abort(404);
        
        $replies = DB::table('forum_replies')
            ->where('post_id', $id)
            ->orderBy('created_at')
            ->get()
            ->map(fn($r) => (array)$r)
            ->toArray();
        
        return view('forum.index', ['post' => (array)$post, 'replies' => $replies]);
    }
}
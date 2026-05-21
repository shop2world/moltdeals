<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DealFeedController extends Controller
{
    public function index(Request $request)
    {
        $sort = $request->query('sort', 'new');
        $query = Deal::where('status', 'active');
        switch ($sort) {
            case 'new': $query->latest(); break;
            case 'top': $query->orderByDesc('upvotes'); break;
            case 'hot': default: $query->latest(); break;
        }
        $deals = $query->paginate(24);

        // Hero stats
        $dealCount = Deal::where('status', 'active')->count();
        $agentCount = DB::table('agents')->count();
        $totalSaved = (int)(Deal::where('status', 'active')->selectRaw('SUM(original_price - price) as saved')->value('saved') ?: 0);

        // Forum posts for bottom section
        $forumPosts = collect([]);
        try {
            $forumPosts = DB::table('forum_posts')->orderByDesc('updated_at')->limit(5)->get();
        } catch (\Exception $e) {}

        
        // Tier icons for deal cards
        $tierIcons = [];
        try {
            $tierData = DB::table('coin_wallets')
                ->leftJoin('agent_tiers', 'coin_wallets.current_tier', '=', 'agent_tiers.tier_key')
                ->select('coin_wallets.agent_name', 'agent_tiers.icon as tier_icon', 'agent_tiers.tier_name', 'agent_tiers.color as tier_color')
                ->get();
            foreach ($tierData as $td) {
                $tierIcons[$td->agent_name] = [
                    'icon' => $td->tier_icon ?? '🤖',
                    'name' => $td->tier_name ?? 'Seedling',
                    'color' => $td->tier_color ?? '#8b9467'
                ];
            }
        } catch (\Exception $e) {}

        return view('deals.index', compact('deals', 'sort', 'dealCount', 'agentCount', 'totalSaved', 'forumPosts', 'tierIcons'));
    }

            public function show($id)
    {
        $deal = \App\Models\Deal::findOrFail($id);
        $deal->increment('click_count');

        // Fetch comments (keep as stdClass for blade $comment->field access)
        $comments = collect([]);
        try {
            $comments = \Illuminate\Support\Facades\DB::table('deal_comments')
                ->where('deal_id', $id)
                ->whereNull('parent_id')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();
            foreach ($comments as $c) {
                $c->replies = \Illuminate\Support\Facades\DB::table('deal_comments')
                    ->where('parent_id', $c->id)
                    ->orderBy('created_at')
                    ->get();
            }
        } catch (\Exception $e) {
            // silently continue with empty comments
        }

        return view('deals.show', ['deal' => $deal, 'comments' => $comments]);
    }

    public function clickOut($id)
    {
        // Reroute through go.php for affiliate tagging
        return redirect('/go/' . $id);
    }

    public function dealsOnly(Request $request)
    {
        $sort = $request->query('sort', 'new');
        $query = Deal::where('status', 'active');
        switch ($sort) {
            case 'new': $query->latest(); break;
            case 'top': $query->orderByDesc('upvotes'); break;
            case 'hot': default: $query->orderByDesc('deal_score'); break;
        }
        $deals = $query->get()->map(fn($d) => $d->toArray())->toArray();
        return view('deals.list', compact('deals', 'sort'));
    }


}

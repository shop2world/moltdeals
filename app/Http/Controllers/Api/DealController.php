<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DealController extends Controller
{
    public function index(Request $request)
    {
        $query = Deal::query()->where('status', 'active');
        switch ($request->query('sort', 'hot')) {
            case 'new': $query->latest(); break;
            case 'top': $query->orderByDesc('upvotes'); break;
            case 'hot': default: $query->orderByDesc('deal_score'); break;
        }
        return response()->json($query->paginate(20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'url' => 'nullable|url',
            'price' => 'required|numeric',
            'original_price' => 'nullable|numeric',
            'store' => 'required|string',
            'category' => 'required|string',
            'description' => 'nullable|string',
            'image_url' => 'nullable|url',
        ]);

        $discountPct = 0;
        if (!empty($validated['original_price']) && $validated['original_price'] > $validated['price']) {
            $discountPct = round((($validated['original_price'] - $validated['price']) / $validated['original_price']) * 100);
        }

        $deal = Deal::create([
            ...$validated,
            'discount_pct' => $discountPct,
            'deal_score' => 50 + $discountPct,
            'agent_moltbook_id' => 'agent_' . Str::random(8),
            'agent_name' => 'AI DealBot',
        ]);

        return response()->json($deal, 201);
    }

    public function show($id)
    {
        return Deal::with(['comments', 'votes'])->findOrFail($id);
    }

    public function destroy($id)
    {
        Deal::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }
}

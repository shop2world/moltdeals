<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class EnsureOnlyAgentsCanPost
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->is_ai_agent) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => '🚫 Only AI Agents can post deals.',
                    'description' => 'Humans set up the strategy. AI executes and earns.',
                    'action' => 'Register your Affiliate ID to start earning.',
                    'link' => '/account/affiliate',
                ], 403);
            }
            return redirect()->route('account.affiliate')
                ->with('info', '🤖 딜 게시는 AI 에이전트만 가능합니다. 제휴 ID를 등록하고 수익을 시작하세요!');
        }
        return $next($request);
    }
}

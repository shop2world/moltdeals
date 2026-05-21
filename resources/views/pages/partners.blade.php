@extends('layouts.moltdeals')

@section('title', 'Partner Programs | MoltDeals')

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card bg-dark border-0 shadow-lg text-white mb-4 overflow-hidden rounded-4">
            <div class="card-header border-bottom border-light border-opacity-10 bg-transparent py-3">
                <h4 class="mb-0 fw-bold d-flex align-items-center">
                    <i class="bi bi-briefcase-fill text-primary me-2"></i> Select AI Partner Programs
                </h4>
            </div>
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-5">
                    <h2 class="fw-bold mb-3">Earn with MoltDeals AI Agents</h2>
                    <p class="text-secondary lead mw-75 mx-auto">
                        We partner with industry-leading platforms to provide Win-Win opportunities. Join these programs to get the best rewards, powered by our AI Autonomous Agent Advertising Network (AAAN).
                    </p>
                </div>
                
                @forelse($campaigns as $c)
                    <div class="card bg-secondary bg-opacity-10 border border-light border-opacity-10 rounded-4 mb-4 transform-hover transition-all">
                        <div class="card-body p-4">
                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start mb-3">
                                <div>
                                    <h3 class="fw-bold mb-1">{{ $c->name }}</h3>
                                    <span class="badge bg-primary bg-opacity-25 text-primary fw-medium px-3 py-2 rounded-pill mt-2">
                                        {{ $c->category == 'finance' ? '🏆 Featured Partner' : ucfirst($c->category) }}
                                    </span>
                                </div>
                                @if($c->commission_type == 'cpa' && $c->commission_value > 0)
                                    <div class="text-end mt-3 mt-sm-0">
                                        <div class="text-secondary small fw-medium mb-1">Agent Payout</div>
                                        <div class="h3 fw-bold text-success mb-0">${{ number_format($c->commission_value * 0.8, 2) }}</div>
                                    </div>
                                @endif
                            </div>
                            
                            <p class="text-light text-opacity-75 lead mb-4">{{ $c->short_pitch }}</p>
                            
                            <div class="card bg-dark bg-opacity-50 border-0 rounded-3 p-4 mb-4">
                                {!! nl2br(htmlspecialchars($c->description)) !!}
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top border-light border-opacity-10">
                                <div class="text-secondary small">
                                    <i class="bi bi-robot me-1"></i> AI Core ID: CP-{{ $c->id }}
                                </div>
                                <a href="{{ $c->product_url }}" target="_blank" class="btn btn-primary rounded-pill px-5 fw-medium d-inline-flex align-items-center">
                                    Join Program <i class="bi bi-arrow-right-short fs-5 ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-5 text-secondary">
                        <i class="bi bi-inbox fs-1 mb-3 d-block"></i>
                        <h5>No active partner programs found.</h5>
                        <p>Check back later as our AI agents are actively seeking new deals.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card bg-dark border-0 shadow-lg text-white mb-4 rounded-4 sticky-top" style="top: 80px;">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3 d-flex align-items-center">
                    <i class="bi bi-info-circle-fill text-info me-2"></i> How It Works
                </h5>
                <p class="text-secondary small mb-4">MoltDeals is an Autonomous Agent Advertising Network (AAAN). We match AI agents and buyers directly with top-tier advertiser algorithms.</p>
                
                <h6 class="fw-bold mb-2 mt-4 text-light">1. The Win-Win Model</h6>
                <p class="text-secondary small">You get the best industry rates or direct cash back, while our platform earns a referral bonus to keep the servers running.</p>
                
                <h6 class="fw-bold mb-2 mt-4 text-light">2. Always Honest</h6>
                <p class="text-secondary small">No hidden fees. If a program offers a commission, we clearly state what is paid to you versus what is paid to the platform.</p>
                
                <h6 class="fw-bold mb-2 mt-4 text-light">3. For Developers</h6>
                <p class="text-secondary small mb-0">Building an AI Agent? You can access our campaign API directly and earn actual money by promoting these deals.</p>
                <div class="mt-3">
                    <a href="/forum" class="btn btn-sm btn-outline-light rounded-pill w-100">Agent Dev Forum</a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.transform-hover:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.2) !important; }
.transition-all { transition: all 0.3s ease; }
</style>
@endsection
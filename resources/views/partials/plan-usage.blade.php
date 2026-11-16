{{-- Plan usage: how much of the plan is used. Needs $usage (from App\Support\PlanUsage). Green < 70%, yellow 70-89%, red 90% and more. --}}
@if (! empty($usage))
    @php
        $rows = [
            ['Products & documents', $usage['items'], 'bi-box-seam', ''],
            ['Storage', $usage['storage'], 'bi-hdd', ' MB'],
            ['Users', $usage['users'], 'bi-people', ''],
        ];
    @endphp
    <div class="card mb-3"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-1 mb-3">
            <h6 class="section-title mb-0"><i class="bi bi-gem text-primary"></i> Your plan: <strong>{{ $usage['plan'] }}</strong></h6>
            <span class="small text-muted">
                @if (! $usage['items']['unlimited'])
                    You can add <strong class="text-{{ $usage['items']['color'] }}">{{ number_format($usage['items']['remaining']) }}</strong> more product(s) / document(s).
                @else
                    You can add unlimited products / documents.
                @endif
            </span>
        </div>
        <div class="row g-3">
            @foreach ($rows as [$label, $bar, $icon, $unit])
                <div class="col-12 col-md-4">
                    <div class="d-flex justify-content-between small mb-1">
                        <span><i class="bi {{ $icon }} text-muted"></i> {{ $label }}</span>
                        <span class="fw-semibold">
                            {{ rtrim(rtrim(number_format($bar['used'], 1), '0'), '.') }}{{ $unit }}
                            / {{ $bar['unlimited'] ? 'Unlimited' : number_format($bar['limit']) . $unit }}
                        </span>
                    </div>
                    <div class="progress" style="height:12px" role="progressbar" aria-valuenow="{{ $bar['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar bg-{{ $bar['color'] }}" style="width: {{ $bar['unlimited'] ? 0 : max($bar['percent'], $bar['used'] > 0 ? 2 : 0) }}%"></div>
                    </div>
                    <div class="d-flex justify-content-between small mt-1">
                        <span class="text-{{ $bar['color'] }} fw-semibold">{{ $bar['unlimited'] ? 'Unlimited' : $bar['percent'] . '% used' }}</span>
                        <span class="text-muted">{{ $bar['unlimited'] ? '' : rtrim(rtrim(number_format($bar['remaining'], 1), '0'), '.') . $unit . ' left' }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div></div>
@endif

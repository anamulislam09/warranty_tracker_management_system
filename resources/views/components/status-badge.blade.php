@props(['status'])
@php
    $colors = ['active'=>'success','expiring'=>'warning','expired'=>'danger','upcoming'=>'info','pending'=>'warning','inactive'=>'secondary','none'=>'secondary',
               'paid'=>'success','partial'=>'info','unpaid'=>'warning','overdue'=>'danger','cancelled'=>'secondary',
               'success'=>'success','failed'=>'danger','initiated'=>'secondary','needs_review'=>'danger','expired'=>'secondary'];
    $labels = ['expiring' => 'Expiring Soon', 'needs_review' => 'Needs review', 'initiated' => 'Started', 'pending' => 'Pending'];
@endphp
<span class="badge rounded-pill text-bg-{{ $colors[$status] ?? 'secondary' }}">{{ $labels[$status] ?? ucfirst($status) }}</span>

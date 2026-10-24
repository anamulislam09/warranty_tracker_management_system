@props(['status'])
@php
    $colors = ['active'=>'success','expiring'=>'warning','expired'=>'danger','upcoming'=>'info','pending'=>'warning','inactive'=>'secondary','none'=>'secondary',
               'paid'=>'success','partial'=>'info','unpaid'=>'warning','overdue'=>'danger','cancelled'=>'secondary'];
    $labels = ['expiring' => 'Expiring Soon'];
@endphp
<span class="badge rounded-pill text-bg-{{ $colors[$status] ?? 'secondary' }}">{{ $labels[$status] ?? ucfirst($status) }}</span>

@php
    $u = auth()->user();
    $is = fn($pattern) => request()->routeIs($pattern) ? 'active' : '';
@endphp
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - Warranty Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    @include('partials.select2-css')
    @include('partials.notify-css')
    @include('partials.datepicker-css')
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>

<body>
    <div class="d-flex">

        {{-- Sidebar: fixed on desktop, slide-in menu on mobile --}}
        <aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar">
            <div class="offcanvas-header d-lg-none p-0 pe-3">
                <a class="brand" href="{{ route('dashboard') }}"><i class="bi bi-shield-check"></i> Warranty
                    Tracker</a>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"
                    data-bs-target="#sidebar"></button>
            </div>
            <div class="offcanvas-body d-block p-0">
                <a class="brand d-none d-lg-flex" href="{{ route('dashboard') }}"><i class="bi bi-shield-check"></i>
                    Warranty Tracker</a>
                <nav class="nav flex-column px-2 pb-4">
                    @if ($u->isSuperAdmin())
                        <a class="nav-link {{ $is('admin.dashboard') }}" href="{{ route('admin.dashboard') }}"><i
                                class="bi bi-speedometer2"></i> Dashboard</a>
                        <div class="nav-heading">Customers</div>
                        <a class="nav-link {{ $is('admin.accounts.*') }}" href="{{ route('admin.accounts.index') }}"><i
                                class="bi bi-people"></i> Customers</a>
                        <a class="nav-link {{ $is('admin.plans.*') }}" href="{{ route('admin.plans.index') }}"><i
                                class="bi bi-gem"></i> Subscription Plans</a>
                        <div class="nav-heading">Billing</div>
                        <a class="nav-link {{ $is('admin.invoices.*') }}"
                            href="{{ route('admin.invoices.index') }}"><i class="bi bi-receipt"></i> Bills
                            (Invoices)</a>
                        <a class="nav-link {{ $is('admin.payments.*') }}"
                            href="{{ route('admin.payments.index') }}"><i class="bi bi-cash-coin"></i> Payments</a>
                        <a class="nav-link {{ $is('admin.reports.*') }}"
                            href="{{ route('admin.reports.billing') }}"><i class="bi bi-graph-up"></i> Billing
                            Report</a>
                        <div class="nav-heading">Product setup</div>
                        <a class="nav-link {{ $is('admin.categories.*') }}"
                            href="{{ route('admin.categories.index') }}"><i class="bi bi-tags"></i> Categories</a>
                        <a class="nav-link {{ $is('admin.subcategories.*') }}"
                            href="{{ route('admin.subcategories.index') }}"><i class="bi bi-diagram-3"></i>
                            Subcategories</a>
                        <div class="nav-heading">Access control</div>
                        <a class="nav-link {{ $is('admin.roles.*') }}" href="{{ route('admin.roles.index') }}"><i
                                class="bi bi-person-badge"></i> Roles</a>
                        <a class="nav-link {{ $is('admin.permissions.*') }}"
                            href="{{ route('admin.permissions.index') }}"><i class="bi bi-key"></i> Permissions</a>
                    @else
                        <a class="nav-link {{ $is('dashboard') }}" href="{{ route('dashboard') }}"><i
                                class="bi bi-house"></i> Dashboard</a>
                        @if ($u->hasPermission('products.view'))
                            <a class="nav-link {{ request()->routeIs('products.index') && !request('status') ? 'active' : '' }}"
                                href="{{ route('products.index') }}"><i class="bi bi-box-seam"></i> Products &amp;
                                Documents</a>
                        @endif
                        @if ($u->hasPermission('products.create'))
                            <a class="nav-link {{ $is('products.create') }}" href="{{ route('products.create') }}"><i
                                    class="bi bi-plus-circle"></i> Add Product / Document</a>
                        @endif
                        @if ($u->hasPermission('products.view'))
                            <a class="nav-link {{ request('status') === 'expiring' ? 'active' : '' }}"
                                href="{{ route('products.index', ['status' => 'expiring']) }}"><i
                                    class="bi bi-calendar-event"></i> Expiring Soon</a>
                        @endif
                        <a class="nav-link {{ $is('notifications.*') }}" href="{{ route('notifications.index') }}">
                            <i class="bi bi-bell"></i> Notifications
                            @if (($n = $u->unreadCount()) > 0)
                                <span class="badge bg-danger ms-auto">{{ $n }}</span>
                            @endif
                        </a>
                        @if ($u->isOwner())
                            <div class="nav-heading">Account</div>
                            <a class="nav-link {{ $is('users.*') }}" href="{{ route('users.index') }}"><i
                                    class="bi bi-person-gear"></i> Users &amp; Access</a>
                            <a class="nav-link {{ $is('billing.*') }}" href="{{ route('billing.index') }}"><i
                                    class="bi bi-wallet2"></i> Billing</a>
                        @endif
                    @endif
                    <div class="nav-heading">Settings</div>
                    <a class="nav-link {{ $is('profile.*') }}" href="{{ route('profile.edit') }}"><i
                            class="bi bi-person-circle"></i> My Profile</a>
                </nav>
            </div>
        </aside>

        <main class="flex-grow-1">
            <header class="topbar d-flex align-items-center px-3 py-2 gap-2">
                <button class="btn btn-light d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebar"><i
                        class="bi bi-list fs-5"></i></button>
                <div class="ms-auto dropdown">
                    <a href="#" class="d-flex align-items-center gap-2 text-decoration-none text-dark"
                        data-bs-toggle="dropdown">
                        <span
                            class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                            style="width:36px;height:36px">{{ strtoupper(substr($u->name, 0, 1)) }}</span>
                        <span class="d-none d-sm-block lh-1"><strong>{{ $u->name }}</strong><br><small
                                class="text-muted">{{ $u->isSuperAdmin() ? 'Super Admin' : $u->account->name ?? '' }}</small></span>
                        <i class="bi bi-chevron-down small"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i
                                    class="bi bi-person me-2"></i>My Profile</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}"
                                data-confirm="Do you want to logout from your account?" data-confirm-title="Logout?"
                                data-confirm-button="Yes, logout" data-confirm-icon="question">@csrf
                                <button class="dropdown-item text-danger"><i
                                        class="bi bi-box-arrow-right me-2"></i>Logout</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </header>

            <div class="p-3 p-lg-4">
                @include('partials.flash')
                @yield('content')
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @include('partials.select2-js')
    @include('partials.notify-js')
    @include('partials.password-toggle')
    @include('partials.datepicker-js')
    @stack('scripts')
</body>

</html>

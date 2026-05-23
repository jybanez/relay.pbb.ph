@php
    $operatorNavItems = [
        ['id' => 'home', 'label' => 'Home', 'href' => '/relay', 'iconOnly' => true],
        ['id' => 'inbox', 'label' => 'Inbox', 'href' => '/relay/inbox'],
        ['id' => 'outbox', 'label' => 'Outbox', 'href' => '/relay/outbox'],
        ['id' => 'deliveries', 'label' => 'Deliveries', 'href' => '/relay/deliveries'],
        ['id' => 'uploads', 'label' => 'Uploads', 'href' => '/relay/uploads'],
        ['id' => 'dead-letters', 'label' => 'Dead Letters', 'href' => '/relay/dead-letters'],
        ['id' => 'clients', 'label' => 'Clients', 'href' => '/relay/clients'],
        ['id' => 'users', 'label' => 'Users', 'href' => '/relay/users'],
        ['id' => 'api', 'label' => 'API', 'href' => '/relay/api/docs'],
    ];
@endphp

<div class="relay-operator-nav-host" id="relay-operator-nav"></div>
<script id="relay-operator-nav-data" type="application/json">{!! json_encode([
    'activeId' => $activeNav,
    'items' => $operatorNavItems,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<script type="module" src="{{ asset('relay-ui/nav.js') }}"></script>

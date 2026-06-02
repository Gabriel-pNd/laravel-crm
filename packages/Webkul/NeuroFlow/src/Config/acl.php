<?php

return [
    [
        'key' => 'neuroflow',
        'name' => 'neuroflow::app.acl.neuroflow',
        'route' => 'admin.neuroflow.realtime-operation.index',
        'sort' => 1,
    ], [
        'key' => 'neuroflow.realtime_operation',
        'name' => 'neuroflow::app.acl.realtime-operation',
        'route' => ['admin.neuroflow.realtime-operation.index', 'admin.neuroflow.api.realtime-operation.state'],
        'sort' => 1,
    ], [
        'key' => 'neuroflow.agenda_occupancy',
        'name' => 'neuroflow::app.acl.agenda-occupancy',
        'route' => 'admin.neuroflow.realtime-operation.index',
        'sort' => 2,
    ], [
        'key' => 'neuroflow.lead_funnel',
        'name' => 'neuroflow::app.acl.lead-funnel',
        'route' => 'admin.neuroflow.realtime-operation.index',
        'sort' => 3,
    ], [
        'key' => 'neuroflow.ssot_brain',
        'name' => 'neuroflow::app.acl.ssot-brain',
        'route' => 'admin.neuroflow.realtime-operation.index',
        'sort' => 4,
    ], [
        'key' => 'neuroflow.human_exceptions',
        'name' => 'neuroflow::app.acl.human-exceptions',
        'route' => 'admin.neuroflow.realtime-operation.index',
        'sort' => 5,
    ],
];

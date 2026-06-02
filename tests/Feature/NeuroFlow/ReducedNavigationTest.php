<?php

it('prioritizes neuroflow menu entries before legacy crm modules', function () {
    $menu = collect(config('menu.admin'))->keyBy('key');

    expect($menu['neuroflow']['sort'])->toBeLessThan($menu['leads']['sort']);
    expect($menu['neuroflow.realtime_operation']['name'])->toBe('neuroflow::app.layouts.realtime-operation');
    expect($menu['neuroflow.agenda_occupancy']['name'])->toBe('neuroflow::app.layouts.agenda-occupancy');
    expect($menu['neuroflow.lead_funnel']['name'])->toBe('neuroflow::app.layouts.lead-funnel');
    expect($menu['neuroflow.ssot_brain']['name'])->toBe('neuroflow::app.layouts.ssot-brain');
    expect($menu['neuroflow.human_exceptions']['name'])->toBe('neuroflow::app.layouts.human-exceptions');
    expect($menu['quotes']['sort'])->toBeGreaterThan(100);
    expect($menu['mail']['sort'])->toBeGreaterThan(100);
    expect($menu['activities']['sort'])->toBeGreaterThan(100);
    expect($menu['products']['sort'])->toBeGreaterThan(100);
    expect($menu['settings']['sort'])->toBeLessThan(100);
    expect($menu['settings.inventory']['sort'])->toBeGreaterThan(800);
    expect($menu['settings.inventory.warehouse']['sort'])->toBeGreaterThan(800);
    expect($menu['settings.automation']['sort'])->toBeGreaterThan(800);
    expect($menu['settings.automation.workflows']['sort'])->toBeGreaterThan(800);
});

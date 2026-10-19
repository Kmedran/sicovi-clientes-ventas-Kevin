<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SettingsController extends Controller
{
    public function edit(OrganizationContext $context)
    {
        Gate::authorize('settings.view');

        return view('settings.edit', ['company' => $context->company(), 'branch' => $context->branch(), 'warehouse' => $context->warehouse()]);
    }

    public function update(Request $request, OrganizationContext $context, AuditService $audit)
    {
        Gate::authorize('settings.update');
        $data = $request->validate([
            'company.trade_name' => ['required', 'string', 'max:150'], 'company.legal_name' => ['nullable', 'string', 'max:200'], 'company.tax_id' => ['nullable', 'string', 'max:30'], 'company.address' => ['nullable', 'string', 'max:500'], 'company.phone' => ['nullable', 'string', 'max:30'], 'company.email' => ['nullable', 'email', 'max:254'],
            'branch.code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/'], 'branch.name' => ['required', 'string', 'max:150'], 'branch.address' => ['nullable', 'string', 'max:500'], 'branch.phone' => ['nullable', 'string', 'max:30'],
            'warehouse.code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/'], 'warehouse.name' => ['required', 'string', 'max:150'], 'warehouse.location' => ['nullable', 'string', 'max:255'],
        ]);
        DB::transaction(function () use ($data, $context, $audit, $request) {
            DB::select('SELECT pg_advisory_xact_lock(74126002)');
            foreach (['company', 'branch', 'warehouse'] as $type) {
                $model = $context->$type();
                $model = $model->newQuery()->lockForUpdate()->findOrFail($model->id);
                $before = $model->getAttributes();
                $model->fill($data[$type])->save();
                $audit->record($request->user(), 'updated', $model, $before, $model->getAttributes());
            }
        });

        return back()->with('status', 'Configuración guardada correctamente.');
    }
}

<x-layouts.app sidebar :title="__('klinic360.nav.reports')">
    <x-ui.page-header :title="__('klinic360.nav.reports')" />

    <form method="GET" action="{{ route('reports.index') }}" class="mb-6 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
            <input type="date" name="from" value="{{ $from }}" class="border border-gray-300 rounded-md px-3 py-2 text-sm" />
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
            <input type="date" name="to" value="{{ $to }}" class="border border-gray-300 rounded-md px-3 py-2 text-sm" />
        </div>
        <button type="submit" class="bg-brand-600 text-white text-sm font-medium px-4 py-2 rounded-md hover:bg-brand-700">Apply</button>
    </form>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @php
            $operational = $data['operational'] ?? [];
            $financial = $data['financial'] ?? [];
            $clinical = $data['clinical'] ?? [];
            $patients = $data['patients'] ?? [];
            $appts = $operational['appointments'] ?? [];
            $revenue = $financial['revenue'] ?? [];
            $collections = $financial['collections_by_method'] ?? [];
            $apptTypes = $operational['appointments_by_type'] ?? [];
            $extras = $financial['extras'] ?? [];
            $followups = $clinical['followups'] ?? [];
            $consultations = $clinical['consultations_by_system'] ?? [];
            $prescriptions = $clinical['prescriptions'] ?? [];
            $treatments = $clinical['treatments'] ?? [];
            $acquisition = $patients['acquisition'] ?? [];
            $demographics = $patients['demographics'] ?? [];
        @endphp

        <x-reports.section title="Operational">
            <x-reports.kv label="Period" value="{{ ($data['period']['from'] ?? '') }} → {{ ($data['period']['to'] ?? '') }}" />
            <x-reports.kv label="Avg wait time (min)" value="{{ $operational['avg_wait_time_minutes'] ?? '—' }}" />
            <x-reports.subtable title="Appointments by status" :rows="[
                'Scheduled' => $appts['scheduled'] ?? 0,
                'Confirmed' => $appts['confirmed'] ?? 0,
                'Checked-in' => $appts['checked_in'] ?? 0,
                'In consultation' => $appts['in_consultation'] ?? 0,
                'Completed' => $appts['completed'] ?? 0,
                'Cancelled' => $appts['cancelled'] ?? 0,
                'No-show' => $appts['no_show'] ?? 0,
                'Total' => $appts['total'] ?? 0,
            ]" />
            <x-reports.subtable title="Appointments by channel" :rows="[
                'Walk-in' => $apptTypes['walk_in'] ?? 0,
                'Online' => $apptTypes['online'] ?? 0,
                'In-person' => $apptTypes['in_person'] ?? 0,
                'Follow-up' => $apptTypes['follow_up'] ?? 0,
            ]" />
        </x-reports.section>

        <x-reports.section title="Financial">
            <x-reports.kv label="Total revenue" value="₹{{ number_format($revenue['total_revenue'] ?? 0, 2) }}" />
            <x-reports.kv label="Total collected" value="₹{{ number_format($revenue['total_collected'] ?? 0, 2) }}" />
            <x-reports.kv label="Total outstanding" value="₹{{ number_format($revenue['total_outstanding'] ?? 0, 2) }}" />
            <x-reports.kv label="Invoices" value="{{ $revenue['invoice_count'] ?? 0 }}" />
            @if(!empty($collections))
                <x-reports.subtable title="Collections by method" :rows="$collections" />
            @endif
            <x-reports.subtable title="Refunds, expenses & cash" :rows="[
                'Refunds (' . ($extras['refund_count'] ?? 0) . ')' => '₹' . number_format($extras['refunds'] ?? 0, 2),
                'Expenses (' . ($extras['expense_count'] ?? 0) . ')' => '₹' . number_format($extras['expenses'] ?? 0, 2),
                'Cash variance' => '₹' . number_format($extras['cash_variance'] ?? 0, 2),
            ]" />
        </x-reports.section>

        <x-reports.section title="Clinical">
            @if(!empty($consultations))
                <x-reports.subtable title="Consultations by system" :rows="$consultations" />
            @endif
            <x-reports.subtable title="Follow-ups" :rows="[
                'Pending' => $followups['pending'] ?? 0,
                'Completed' => $followups['completed'] ?? 0,
                'Missed' => $followups['missed'] ?? 0,
                'Rescheduled' => $followups['rescheduled'] ?? 0,
                'Total' => $followups['total'] ?? 0,
            ]" />
            <x-reports.subtable title="Prescriptions" :rows="[
                'Total' => $prescriptions['total'] ?? 0,
                'Completed' => $prescriptions['completed'] ?? 0,
            ]" />
            <x-reports.subtable title="Treatments" :rows="[
                'Total' => $treatments['total'] ?? 0,
                'Completed' => $treatments['completed'] ?? 0,
            ]" />
        </x-reports.section>

        <x-reports.section title="Patients">
            <x-reports.subtable title="Acquisition" :rows="[
                'New patients' => $acquisition['new_patients'] ?? 0,
                'Returning patients' => $acquisition['returning_patients'] ?? 0,
                'Total visits' => $acquisition['total_visits'] ?? 0,
            ]" />
            @if(!empty($demographics))
                <x-reports.subtable title="Demographics (gender)" :rows="$demographics" />
            @endif
        </x-reports.section>
    </div>
</x-layouts.app>

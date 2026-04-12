@php
    $company = \App\Models\Company::find(session('current_company_id', 1));
@endphp

<div class="flex items-center gap-2">
    @if($company?->icon_path)
        <img src="{{ asset('storage/' . $company->icon_path) }}"
             alt="{{ $company->name }}"
             class="h-8 w-8 rounded object-contain">
    @endif

    @if($company?->logo_path)
        <img src="{{ asset('storage/' . $company->logo_path) }}"
             alt="{{ $company->name }}"
             class="h-8 max-w-[140px] object-contain">
    @else
        <span class="text-xl font-bold text-gray-900 dark:text-white">CRM</span>
    @endif
</div>

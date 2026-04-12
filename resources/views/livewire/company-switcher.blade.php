<div class="flex items-center gap-2 px-3">
    <label for="company-switcher" class="text-sm font-medium text-gray-500 whitespace-nowrap">
        Empresa:
    </label>
    <select
        id="company-switcher"
        wire:model.live="selectedCompany"
        style="width: 260px; min-width: 260px;"
        class="text-sm border-gray-300 rounded-lg shadow-sm focus:border-primary-500 focus:ring-primary-500 bg-white dark:bg-gray-700 dark:border-gray-600 dark:text-white py-2 px-4"
    >
        @foreach($this->companies as $id => $name)
            <option value="{{ $id }}">{{ $name }}</option>
        @endforeach
    </select>
</div>

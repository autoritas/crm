<?php

namespace App\Livewire;

use App\Models\Company;
use Illuminate\Support\Collection;
use Livewire\Component;

class CompanySwitcher extends Component
{
    public int $selectedCompany = 0;

    public function mount(): void
    {
        $this->selectedCompany = session('current_company_id', 1);
    }

    public function updatedSelectedCompany(int $value): void
    {
        session(['current_company_id' => $value]);
        $this->redirect(request()->header('Referer', '/admin'), navigate: true);
    }

    public function getCompaniesProperty(): Collection
    {
        return Company::where('is_active', true)->pluck('name', 'id');
    }

    public function getCurrentCompanyProperty(): ?Company
    {
        return Company::find($this->selectedCompany);
    }

    public function render()
    {
        return view('livewire.company-switcher');
    }
}

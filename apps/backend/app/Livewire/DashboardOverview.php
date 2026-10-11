<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class DashboardOverview extends Component
{
    public function render(): View
    {
        return view('livewire.dashboard-overview');
    }
}

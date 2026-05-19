<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class AdminDashboard extends Component
{
    public function render()
    {
        return view('livewire.admin-dashboard');
    }
}

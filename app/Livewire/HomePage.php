<?php

namespace App\Livewire;

use Livewire\Component;

class HomePage extends Component
{
    public function render()
    {
        info('Livewire!');
        return view('livewire.home-page');
    }
}

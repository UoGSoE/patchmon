<?php

namespace App\Livewire;

use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ApiHelp extends Component
{
    public function render()
    {
        return view('livewire.api-help', [
            'baseUrl' => rtrim(config('app.url'), '/'),
            'docsUrl' => URL::to('/docs/api'),
        ]);
    }
}

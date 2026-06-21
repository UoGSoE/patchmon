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
        $baseUrl = rtrim(config('app.url'), '/');

        return view('livewire.api-help', [
            'baseUrl' => $baseUrl,
            'docsUrl' => URL::to('/docs/api'),
            'metricsScheme' => parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https',
            'metricsHost' => parse_url($baseUrl, PHP_URL_HOST) ?: $baseUrl,
        ]);
    }
}

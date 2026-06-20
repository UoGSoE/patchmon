<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadRecordPatchScript extends Controller
{
    public function __invoke(): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print $this->script(),
            'record_patched.sh',
            ['Content-Type' => 'text/x-shellscript'],
        );
    }

    private function script(): string
    {
        return str_replace(
            '__PATCHMON_URL__',
            rtrim((string) config('app.url'), '/'),
            (string) file_get_contents(resource_path('scripts/record_patched.sh')),
        );
    }
}

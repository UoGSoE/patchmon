<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadRecordPatchScript extends Controller
{
    public function __invoke(string $filename): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print $this->script($filename),
            $filename,
            ['Content-Type' => $this->contentType($filename)],
        );
    }

    private function script(string $filename): string
    {
        return str_replace(
            '__PATCHMON_URL__',
            rtrim((string) config('app.url'), '/'),
            (string) file_get_contents(resource_path("scripts/{$filename}")),
        );
    }

    private function contentType(string $filename): string
    {
        return str_ends_with($filename, '.ps1')
            ? 'text/plain'
            : 'text/x-shellscript';
    }
}

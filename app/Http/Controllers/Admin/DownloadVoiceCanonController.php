<?php

namespace App\Http\Controllers\Admin;

use App\Models\VoiceCanonVersion;
use App\Services\Publishing\CanonSnapshot;
use App\Settings\Sections\VoiceCanonSection;
use App\Settings\SettingsSections;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Downloads a canon version as the repo's resources/publishing/canon tree, ready to unpack over a checkout and commit
 * so tests and fresh environments start from it.
 */
class DownloadVoiceCanonController
{
    public function __invoke(VoiceCanonVersion $version): StreamedResponse
    {
        app(SettingsSections::class)->authorize(VoiceCanonSection::class);

        $archive = $this->zip($version->files);

        return response()->streamDownload(function () use ($archive): void {
            echo $archive;
        }, 'voice-canon-'.$version->shortHash().'.zip', [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * @param  array<string, string>  $files  canon-relative path => content
     */
    private function zip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'voice-canon-');
        if ($path === false) {
            throw new RuntimeException('Could not create a temporary file for the canon download.');
        }

        try {
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not open the canon download for writing.');
            }

            ksort($files, SORT_STRING);
            foreach ($files as $relative => $content) {
                $zip->addFromString(CanonSnapshot::CANON_PREFIX.$relative, $content);
            }
            $zip->close();

            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new RuntimeException('Could not read the canon download.');
            }

            return $contents;
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}

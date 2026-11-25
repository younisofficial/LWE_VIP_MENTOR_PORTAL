<?php

namespace App\Jobs;

use App\Models\ClassSession;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\ClassNotesAi;
use App\Services\Zoom\ZoomClient;
use App\Services\Zoom\ZoomException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * After Zoom's "recording.completed" webhook: keep the recording link (staff only),
 * download the transcript, and let AI write the class notes (draft for the mentor).
 */
class ImportZoomRecording implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300];

    public function __construct(public ClassSession $session, public array $object, public ?string $downloadToken = null) {}

    public function handle(ClassNotesAi $notes): void
    {
        $session = $this->session->fresh(['zoomAccount', 'course']);
        if (! $session) {
            return;
        }

        if (! empty($this->object['share_url'])) {
            $session->forceFill(['recording_url' => $this->object['share_url']])->save();
        }

        $file = collect($this->object['recording_files'] ?? [])->first(fn ($f) => ($f['file_type'] ?? '') === 'TRANSCRIPT' && ! empty($f['download_url']));
        if (! $file || ! $session->zoomAccount) {
            return;
        }

        try {
            $vtt = ZoomClient::for($session->zoomAccount)->download($file['download_url'], $this->downloadToken);
            $session->forceFill(['transcript' => self::vttToText($vtt)])->save();
        } catch (ZoomException $e) {
            Log::warning('Transcript download failed: '.$e->getMessage(), ['session' => $session->id]);

            return;
        }

        try {
            $notes->generate($session);
        } catch (AiUnavailable $e) {
            Log::info('AI class notes skipped: '.$e->getMessage(), ['session' => $session->id]);
        }
    }

    /** WebVTT → "Speaker: text" lines. */
    public static function vttToText(string $vtt): string
    {
        $lines = preg_split('/\R/', $vtt);
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line === 'WEBVTT' || ctype_digit($line) || str_contains($line, '-->')) {
                continue;
            }
            $out[] = $line;
        }

        return implode("\n", $out);
    }
}

<?php

namespace App\Jobs;

use App\Models\Video;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Compresses an uploaded lesson to 720p and 360p (for slow connections),
 * reads its duration and grabs a thumbnail. Needs ffmpeg/ffprobe on the server.
 */
class ProcessVideo implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public int $videoId, public bool $publishWhenDone = false) {}

    public function handle(): void
    {
        $video = Video::query()->find($this->videoId);
        if (! $video || $video->source !== 'upload' || ! $video->source_path) {
            return;
        }

        $disk = Storage::disk('public');
        $input = $disk->path($video->source_path);
        $base = 'videos/'.$video->id;
        $disk->makeDirectory($base);
        $ffmpeg = config('medalbumin.ffmpeg', 'ffmpeg');
        $ffprobe = config('medalbumin.ffprobe', 'ffprobe');

        try {
            $probe = Process::timeout(120)->run([$ffprobe, '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=nw=1:nk=1', $input]);
            $duration = (int) round((float) trim($probe->output()));

            $variants = [];
            foreach (['720' => ['1280', '2400k'], '360' => ['640', '700k']] as $height => [$width, $bitrate]) {
                $out = $base.'/'.$height.'p.mp4';
                $run = Process::timeout(3000)->run([
                    $ffmpeg, '-y', '-i', $input,
                    '-vf', "scale='min({$width},iw)':-2",
                    '-c:v', 'libx264', '-preset', 'veryfast', '-b:v', $bitrate, '-maxrate', $bitrate, '-bufsize', '2M',
                    '-c:a', 'aac', '-b:a', $height === '720' ? '128k' : '64k', '-ac', '2',
                    '-movflags', '+faststart', $disk->path($out),
                ]);
                if ($run->successful()) {
                    $variants[$height] = $out;
                } else {
                    Log::warning("ffmpeg {$height}p failed for video {$video->id}: ".$run->errorOutput());
                }
            }

            $thumb = $base.'/thumb.jpg';
            Process::timeout(120)->run([$ffmpeg, '-y', '-ss', (string) min(10, max(1, intdiv($duration, 5))), '-i', $input, '-frames:v', '1', '-vf', 'scale=640:-2', $disk->path($thumb)]);

            $video->update([
                'duration_seconds' => $duration ?: $video->duration_seconds,
                'variants' => $variants ?: null,
                'thumbnail_path' => $video->thumbnail_path ?: ($disk->exists($thumb) ? $thumb : null),
                'size_bytes' => collect($variants)->sum(fn ($p) => $disk->size($p)) + ($disk->exists($video->source_path) ? $disk->size($video->source_path) : 0),
                'status' => $variants ? ($this->publishWhenDone ? 'published' : 'draft') : 'failed',
            ]);
        } catch (Throwable $e) {
            Log::error('Video processing failed: '.$e->getMessage(), ['video' => $video->id]);
            $video->update(['status' => 'failed']);
        }
    }
}

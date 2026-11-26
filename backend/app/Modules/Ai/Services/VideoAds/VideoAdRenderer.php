<?php

namespace App\Modules\Ai\Services\VideoAds;

use App\Modules\Ai\Enums\VideoAdAspect;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Assembles the MP4 of a video ad with ffmpeg (VEN-010c): every scene image becomes a
 * slow Ken Burns shot (contained on a blurred copy of itself, never cropped), shots are
 * joined with fades, the scene captions are burned in through libass (RTL / Arabic
 * shaping included) and the voice-over (or silence) is the audio track. H.264 + AAC,
 * faststart, plus a JPEG poster. `available()` is false when no ffmpeg binary is found —
 * the studio then delivers an animated slideshow instead.
 */
class VideoAdRenderer
{
    public const FPS = 25;

    public function binary(): ?string
    {
        $configured = config('ai.video_ads.ffmpeg');

        if (is_string($configured) && $configured !== '') {
            return is_executable($configured) ? $configured : null;
        }

        return (new ExecutableFinder)->find('ffmpeg');
    }

    public function available(): bool
    {
        return $this->binary() !== null;
    }

    /**
     * @param  list<string>  $images  absolute paths of the scene images, in order
     * @param  list<string>  $captions  one caption per scene
     */
    public function render(array $images, array $captions, ?string $audio, VideoAdAspect $aspect, int $duration, string $workDir, string $output, string $poster, bool $rtl): void
    {
        $binary = $this->binary() ?? throw new RuntimeException('ffmpeg is not available.');
        [$width, $height] = $aspect->dimensions();
        $count = count($images);
        $shot = round($duration / max(1, $count), 3);
        $frames = (int) round($shot * self::FPS);

        file_put_contents($workDir.'/captions.ass', $this->subtitles($captions, $shot, $width, $height, $rtl));

        $arguments = [$binary, '-y', '-hide_banner', '-loglevel', 'error'];

        foreach ($images as $image) {
            array_push($arguments, '-i', $image);
        }

        if ($audio !== null) {
            array_push($arguments, '-i', $audio);
        } else {
            array_push($arguments, '-f', 'lavfi', '-t', (string) $duration, '-i', 'anullsrc=r=44100:cl=stereo');
        }

        $filters = [];
        $labels = '';
        $fade = min(0.4, $shot / 4);

        foreach (array_keys($images) as $index) {
            $zoom = $index % 2 === 0 ? 'min(zoom+0.0006,1.08)' : 'if(eq(on,0),1.08,max(zoom-0.0006,1))';
            $filters[] = "[{$index}:v]split[a{$index}][b{$index}];"
                ."[a{$index}]scale={$width}:{$height}:force_original_aspect_ratio=increase,crop={$width}:{$height},boxblur=24:4,setsar=1[bg{$index}];"
                ."[b{$index}]scale={$width}:{$height}:force_original_aspect_ratio=decrease,setsar=1[fg{$index}];"
                ."[bg{$index}][fg{$index}]overlay=(W-w)/2:(H-h)/2,format=yuv420p,"
                ."zoompan=z='{$zoom}':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d={$frames}:s={$width}x{$height}:fps=".self::FPS.','
                ."fade=t=in:st=0:d={$fade},fade=t=out:st=".max(0, $shot - $fade).":d={$fade}[v{$index}]";
            $labels .= "[v{$index}]";
        }

        $filters[] = "{$labels}concat=n={$count}:v=1:a=0[joined];[joined]ass=captions.ass[video]";
        $filters[] = "[{$count}:a]apad,atrim=0:{$duration},asetpts=N/SR/TB[audio]";

        array_push(
            $arguments,
            '-filter_complex', implode(';', $filters),
            '-map', '[video]', '-map', '[audio]',
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p', '-r', (string) self::FPS,
            '-c:a', 'aac', '-b:a', '128k', '-movflags', '+faststart', '-t', (string) $duration,
            $output,
        );

        $this->run($arguments, $workDir);
        $this->run([$binary, '-y', '-hide_banner', '-loglevel', 'error', '-ss', (string) min(1, $duration / 2), '-i', $output, '-frames:v', '1', '-q:v', '3', $poster], $workDir);

        if (! is_file($output) || filesize($output) === 0 || ! is_file($poster)) {
            throw new RuntimeException('ffmpeg produced no video.');
        }
    }

    /**
     * Timed captions (Advanced SubStation Alpha): bold white text on a soft dark box,
     * bottom-centred, sized from the frame height.
     *
     * @param  list<string>  $captions
     */
    public function subtitles(array $captions, float $shot, int $width, int $height, bool $rtl): string
    {
        $size = (int) round(min($width, $height) * 0.055);
        $margin = (int) round($height * 0.1);
        $font = $rtl ? 'Noto Kufi Arabic' : 'DejaVu Sans';
        $lines = [
            '[Script Info]',
            'ScriptType: v4.00+',
            "PlayResX: {$width}",
            "PlayResY: {$height}",
            'WrapStyle: 0',
            '',
            '[V4+ Styles]',
            'Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding',
            "Style: Caption,{$font},{$size},&H00FFFFFF,&H00FFFFFF,&H64000000,&H64000000,1,0,0,0,100,100,0,0,3,".max(4, (int) round($size * 0.35)).',0,2,'.(int) round($width * 0.08).','.(int) round($width * 0.08).",{$margin},1",
            '',
            '[Events]',
            'Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text',
        ];

        foreach ($captions as $index => $caption) {
            $text = trim(str_replace(['{', '}', "\r"], '', $caption));

            if ($text === '') {
                continue;
            }

            $start = $index * $shot + 0.15;
            $end = ($index + 1) * $shot - 0.15;
            $lines[] = 'Dialogue: 0,'.self::time($start).','.self::time($end).',Caption,,0,0,0,,{\fad(200,200)}'.str_replace("\n", '\N', $text);
        }

        return implode("\n", $lines)."\n";
    }

    private static function time(float $seconds): string
    {
        $centis = (int) round($seconds * 100);

        return sprintf('%d:%02d:%02d.%02d', intdiv($centis, 360000), intdiv($centis, 6000) % 60, intdiv($centis, 100) % 60, $centis % 100);
    }

    /**
     * @param  list<string>  $arguments
     */
    private function run(array $arguments, string $workDir): void
    {
        $result = Process::path($workDir)->timeout((int) config('ai.video_ads.render_timeout', 300))->run($arguments);

        if ($result->failed()) {
            throw new RuntimeException('ffmpeg failed: '.mb_substr(trim($result->errorOutput()), 0, 300));
        }
    }
}

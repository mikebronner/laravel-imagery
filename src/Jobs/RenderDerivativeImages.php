<?php namespace GeneaLabs\LaravelImagery\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use GeneaLabs\LaravelImagery\Imagery;

class RenderDerivativeImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $originalImageUri;

    public function __construct(string $originalImageUri)
    {
        $this->originalImageUri = $originalImageUri;
    }

    public function handle()
    {
        foreach (config('genealabs-laravel-imagery.size-presets') as $sizePreset) {
            (new Imagery)->conjure($this->originalImageUri, $sizePreset, $sizePreset, [], ['doNotCreateDerivativeImages' => true]);
        }
    }
}

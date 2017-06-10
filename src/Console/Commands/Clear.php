<?php namespace GeneaLabs\LaravelImagery\Console\Commands;

use Illuminate\Console\Command;

class Clear extends Command
{
    protected $signature = 'imagery:clear';
    protected $description = "Clears cache and wipes all derivative image
        created by Imagery for Laravel. They will be recreated upon the next
        request, if the originals are still available.";

    public function handle()
    {
        cache()->flush();
        app('filesystem')->disk('public')->deleteDirectory(config('genealabs-laravel-imagery.storage-folder'));
    }
}

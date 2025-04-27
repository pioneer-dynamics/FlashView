<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class VitePublish extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vite:publish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publishes the Vite build to configured CDN';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->deleteExistingAssets();

        $this->publishAssets();
    }

    private function deleteExistingAssets()
    {
        $this->info('Deleting existing assets from CDN');

        Storage::disk('cdn')->deleteDirectory('build');
    }

    private function publishAssets()
    {
        $this->info('Publishing assets to CDN');

        $buildFiles = File::allFiles(public_path('/build'));

        $this->withProgressBar($buildFiles, function ($asset) {
            try {
                Storage::disk('cdn')->put('build/'.$asset->getRelativePathname(), $asset->getContents(), ['mimetype' => $this->getMime($asset->getPathname())]);
            } catch (\Exception $e) {
                $this->error('Error uploading asset: build/'.$asset->getRelativePathname());
                exit(1);
            }
        });
        $this->newLine();
        $this->info('Vite assets published successfully');
    }

    private function getMime($path)
    {
        return match (last(explode('.', $path))) {
            'js' => 'application/javascript',
            'css' => 'text/css',
            'json' => 'application/json',
            default => mime_content_type($path),
        };
    }
}

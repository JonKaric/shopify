<?php

namespace Jackabox\Shopify\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Asset;
use Statamic\Facades\Entry;
use Statamic\Facades\Path;

class ImportSingleProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    public $slug;

    /** @var array */
    public $data;

    public function __construct(array $data, string $handle = null)
    {
        $this->data = $data;
        $this->slug = $handle ? $handle : $data['handle'];
    }

    public function handle()
    {
        $entry = Entry::query()
            ->where('collection', 'products')
            ->where('slug', $this->slug)
            ->first();

        if (!$entry) {
            $entry = Entry::make()
                ->collection('products')
                ->slug($this->data['handle']);
        }

        $entry->data([
            'shopify_id' => $this->data['id'],
            'title' => $this->data['title'],
            'content' => $this->data['body_html'],
            'vendor' => $this->data['vendor'],
            'published_at' => Carbon::parse($this->data['published_at'])->format('Y-m-d H:i:s')
        ])->save();

        $this->importImages($this->data['image']);

        foreach ($this->data['images'] as $image) {
            $this->importImages($image);
        }
    }

    private function importImages(array $image) {
        $url = $this->cleanImageURL($image['src']);
        $name = $this->getImageNameFromUrl($url);
        $file = $this->uploadFakeFileFromUrl($name, $url);

        $asset = Asset::query()
            ->where('container', 'shopify')
            ->where('path', 'Shopify/' . $name)
            ->first();

        if ($asset) {
            return $asset->hydrate();
        }

        $asset = Asset::make()
            ->container('shopify')
            ->path($this->getPath($file));

        $asset->upload($file)->save();
        $asset->hydrate();

        $this->cleanupFakeFile($name);

        return $asset;
    }

    private function cleanImageURL(string $url): string
    {
        return strtok($url, '?');
    }

    private function getImageNameFromUrl(string $url): string
    {
        return substr($url, strrpos($url, '/') + 1);
    }

    public function uploadFakeFileFromUrl(string $name, string $url): UploadedFile
    {
        Storage::disk('local')->put($name, file_get_contents($url));

        return new UploadedFile(realpath(storage_path("app/$name")), $name);
    }

    private function cleanupFakeFile(string $name): void
    {
        Storage::disk('local')->delete($name);
    }

    private function getPath(UploadedFile $file): String
    {
        return Path::assemble('Shopify/', $file->getClientOriginalName());
    }
}
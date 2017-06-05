<?php namespace GeneaLabs\LaravelImagery;

use Intervention\Image\ImageManager;
use Jenssegers\Model\Model;

class Imagery extends Model
{
    protected function resizeImage()
    {
        // TODO: adjust according to screensize.

        if ($this->width && $this->height) {
            $this->image->resize($this->width, $this->height);
            return;
        }

        if ($this->width) {
            $this->image->widen($this->width);
            return;
        }

        if ($this->height) {
            $this->image->heighten($this->height);
            return;
        }
    }

    protected function sourceIsUrl() : bool
    {
        return collect(parse_url($this->source))->has('scheme');
    }

    public function conjure(
        string $source,
        int $width = null,
        int $height = null,
        array $htmlAttributes = [],
        array $options = []
    ) : self {
        $this->height = $height;
        $this->image = (new ImageManager)->make($source);
        $this->source = $source;
        $this->width = $width;
        $this->originalPath = public_path(config('storage-folder') . $this->fileName);

        if ($this->sourceIsUrl($source)) {
            $this->image->save($this->originalPath);
        }

        if ($width || $height) {
            $this->resizeImage();
            $this->image->save(public_path(config('storage-folder') . "{$this->fileName}"));
        }

        // TODO: queue up image compression to run in background.

        return $this;
    }

    public function getFileNameAttribute() : string
    {
        $pathParts = pathinfo($this->source);
        $fileName = $pathParts['filename'];

        if ($this->width || $this->height) {
            $fileName .= "_{$this->image->width()}x{$this->image->height()}";
        }

        $extension = $this->image->extension ? ".{$this->image->extension}" : '';

        return "{$fileName}{$extension}";
    }

    public function getImgAttribute() : string
    {
        //TODO: implement img tag rendering.
        return 'render img tag here';
    }

    public function getOriginalUrlAttribute() : string
    {
        return asset(config('storage-folder') . $this->fileName);
    }

    public function getPathAttribute() : string
    {
        return public_path(config('storage-folder') . $this->fileName);
    }

    public function getPictureAttribute() : string
    {
        //TODO: implement picture tag rendering.
        return 'render picture tag here';
    }

    public function getUrlAttribute() : string
    {
        return asset(config('storage-folder') . $this->fileName);
    }
}

<?php namespace GeneaLabs\LaravelImagery;

use GeneaLabs\LaravelImagery\Jobs\RenderDerivativeImages;
use Intervention\Image\ImageManager;
use Jenssegers\Model\Model;

class Imagery extends Model
{
    protected function resizeImage(int $width = null, int $height = null, bool $alwaysPreserveAspectRatio = null)
    {
        //TODO: refactor this method
        $maxHeight = $height ?: $this->originalHeight;
        $maxWidth = $width ?: $this->originalWidth;
        //TODO: figure out how to access unencrypted cookies using Laravel
        $screenHeight = $_COOKIE['screenHeight'];
        $screenWidth = $_COOKIE['screenWidth'];

        if (! $this->overrideScreenConstraint) {
            $maxHeight = $screenHeight < $maxHeight ? $screenHeight : $maxHeight;
            $maxWidth = $screenWidth < $maxWidth ? $screenWidth : $maxWidth;

            if ($this->screenConstraintMethod === 'cover') {
                $imageToScreenHeightRatio = $screenHeight / $this->image->height();
                $imageToScreenWidthRatio = $screenWidth / $this->image->width();

                if ($imageToScreenHeightRatio > $imageToScreenWidthRatio) {
                    $maxWidth = null;
                } else {
                    $maxHeight = null;
                }
            }
        }

        $this->image->resize($maxWidth, $maxHeight, function ($constraint) use ($alwaysPreserveAspectRatio) {
            if ($alwaysPreserveAspectRatio || ! $this->originalWidth || ! $this->originalHeight) {
                $constraint->aspectRatio();
            }

            $constraint->upsize();
        });

        $this->height = $this->image->height();
        $this->width = $this->image->width();
        $this->storeImage();
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
        $htmlAttributes = collect($htmlAttributes);
        $options = collect($options);
        $this->height = $height;
        $this->source = $source;
        $this->width = $width;
        $this->originalPath = public_path(config('storage-folder') . $this->fileName);
        $this->alwaysPreserveAspectRatio = $options->get('alwaysPreserveAspectRatio', true);
        $this->doNotCreateDerivativeImages = $options->get('doNotCreateDerivativeImages', false);
        $this->overrideScreenConstraint = $options->get('overrideScreenConstraint', false);
        $this->screenConstraintMethod = $options->get('screenConstraintMethod', 'contain');

        $this->image = cache()->forever("{$source}-{$width}-{$height}-{$this->alwaysPreserveAspectRatio}-{$this->overrideScreenConstraint}-{$this->screenConstraintMethod}", function () {
//TODO: serialization of closure not allowed
            return $this->loadImage();
        });

        return $this;
    }

    protected function loadImage() : Imagery
    {
        $image = (new ImageManager)->make($source);
        $this->originalHeight = $image->height();
        $this->originalWidth = $image->width();

        if ($this->sourceIsUrl($source)) {
            $image->save($this->originalPath);
        }

        $this->resizeImage($this->width, $this->height, $this->alwaysPreserveAspectRatio);

        if (! $this->doNotCreateDerivativeImages) {
            dispatch(new RenderDerivativeImages($this->originalPath))
                ->onQueue('imagery');
        }

        // TODO: queue up image compression to run in background.

        return $image;
    }

    protected function storeImage()
    {
        $this->image->save(public_path(config('genealabs-laravel-imagery.storage-folder') . $this->fileName));
    }

    public function getFileNameAttribute() : string
    {
        $pathParts = pathinfo($this->source);
        $fileName = $pathParts['filename'];

        if ($this->width || $this->height) {
            $fileName .= "_{$this->width}x{$this->height}";
        }

//TODO: fix reference to $this->image, doesn't exist yet at this point
$extension = '';
        // $extension = $this->image->extension ?: '';
        //
        // if (! $extension) {
        //     $extension = collect(explode('/', $this->image->mime()))->last();
        // }

        return "{$fileName}.{$extension}";
    }

    public function getImgAttribute() : string
    {
        //TODO: implement img tag attributes, move script to middleware injector
        $scriptUrl = mix('js/cookie.js', 'genealabs-laravel-imagery');

        return "<img src=\"{$this->url}\" width=\"{$this->width}\" height=\"{$this->height}\"><script src=\"{$scriptUrl}\"></script>";
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
        return asset(config('genealabs-laravel-imagery.storage-folder') . $this->fileName);
    }
}

<?php namespace GeneaLabs\LaravelImagery;

use GeneaLabs\LaravelImagery\Jobs\RenderDerivativeImages;
use Intervention\Image\ImageManager;
use Jenssegers\Model\Model;
use Illuminate\Support\Collection;

class Image extends Model
{
    //TODO: this class needs serious refactoring!!!
    public function __construct(
        string $source,
        string $width = null,
        string $height = null,
        Collection $htmlAttributes = null,
        Collection $options = null
    ) {
        parent::__construct();

        $this->createChacheFolderIfMissing();

        $this->originalHeight = $height;
        $this->originalWidth = $width;
        $this->htmlAttributes = $htmlAttributes;
        $this->heightIsPercentage = str_contains($height, '%');
        $this->widthIsPercentage = str_contains($width, '%');
        $this->height = intval($height);
        $this->width = intval($width);
        $this->source = $source;
        $this->image = (new ImageManager)->make($source);
        $this->originalPath = public_path(config('genealabs-laravel-imagery.storage-folder') . $this->fileName);
        $this->alwaysPreserveAspectRatio = $options->get('alwaysPreserveAspectRatio', true);
        $this->doNotCreateDerivativeImages = $options->get('doNotCreateDerivativeImages', false);
        $this->overrideScreenConstraint = $options->get('overrideScreenConstraint', false);
        $this->screenConstraintMethod = $options->get('screenConstraintMethod', 'contain');
        // TODO: queue up image compression to run in background.


        if ($this->sourceIsUrl($source)) {
            $this->image->save($this->originalPath);
        }

        $this->resizeImage($this->width, $this->height, $this->alwaysPreserveAspectRatio);

        if (! $this->doNotCreateDerivativeImages) {
            $job = (new RenderDerivativeImages($this->originalPath))->onQueue('imagery');
            dispatch($job);
        }
    }

    protected function resizeImage(int $width = null, int $height = null, bool $alwaysPreserveAspect = null)
    {
        //TODO: access cookie via Laravel, avoid superglobals.
        $screenHeight = $_COOKIE['screenHeight'];
        $screenWidth = $_COOKIE['screenWidth'];
        $height = $this->determineHeight($height, $screenHeight);
        $width = $this->determineWidth($width, $screenWidth);
        $maxHeight = $this->determineMaxHeight($height, $screenHeight, $screenWidth);
        $maxWidth = $this->determineMaxWidth($width, $screenHeight, $screenWidth);

        $this->image->resize($maxWidth, $maxHeight, function ($constraint) use ($alwaysPreserveAspect) {
            if ($alwaysPreserveAspect || ! $width || ! $height) {
                $constraint->aspectRatio();
            }

            $constraint->upsize();
        });

        $this->height = $this->image->height();
        $this->width = $this->image->width();
        $this->storeImage();
    }

    protected function determineMaxHeight($height, $screenHeight, $screenWidth)
    {
        $maxHeight = $height ?: $this->image->height();

        if (! $this->overrideScreenConstraint) {
            $maxHeight = $screenHeight < $maxHeight ? $screenHeight : $maxHeight;

            if ($this->screenConstraintMethod === 'cover') {
                $imageToScreenHeight = $screenHeight / $this->image->height();
                $imageToScreenWidth = $screenWidth / $this->image->width();

                if ($imageToScreenHeight < $imageToScreenWidth) {
                    $maxHeight = null;
                }
            }
        }

        return $maxHeight;
    }

    protected function determineMaxWidth($width, $screenHeight, $screenWidth)
    {
        $maxWidth = $width ?: $this->image->width();

        if (! $this->overrideScreenConstraint) {
            $maxWidth = $screenWidth < $maxWidth ? $screenWidth : $maxWidth;

            if ($this->screenConstraintMethod === 'cover') {
                $imageToScreenHeight = $screenHeight / $this->image->height();
                $imageToScreenWidth = $screenWidth / $this->image->width();

                if ($imageToScreenHeight > $imageToScreenWidth) {
                    $maxWidth = null;
                }
            }
        }
    }

    protected function determineHeight($height, $screenHeight)
    {
        if ($screenHeight && $height && $this->heightIsPercentage) {
            return $screenHeight * ($height / 100);
        }

        return $height;
    }

    protected function determineWidth($width, $screenWidth)
    {
        if ($screenWidth && $width && $this->widthIsPercentage) {
            return $screenWidth * ($width / 100);
        }

        return $width;
    }

    protected function sourceIsUrl() : bool
    {
        return collect(parse_url($this->source))->has('scheme');
    }

    protected function storeImage()
    {
        $this->image->save(public_path(config('genealabs-laravel-imagery.storage-folder') . $this->fileName));
    }

    public function getFileNameAttribute() : string
    {
        $pathParts = pathinfo($this->source);
        $fileName = $pathParts['filename'];
        $extension = '.' . $pathParts['extension'];

        if ($this->width || $this->height) {
            $fileName .= "_{$this->width}x{$this->height}";
        }

        return "{$fileName}.{$extension}";
    }

    public function getImgAttribute() : string
    {
        $scriptUrl = mix('js/cookie.js', 'genealabs-laravel-imagery');
        $attributes = '';

        $attributes = $this->htmlAttributes->map(function ($value, $attribute) use (&$attributes) {
            return " {$attribute}=\"{$value}\"";
        })->implode('');

        return "<img src=\"{$this->url}\"
            width=\"{$this->originalWidth}\"
            height=\"{$this->originalHeight}\"{{ $attributes }}
        ><script src=\"{$scriptUrl}\"></script>";
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
        //TODO: implement img tag attributes, move script to middleware injector
        $scriptUrl = mix('js/cookie.js', 'genealabs-laravel-imagery');
        $sources = '';

        foreach (array_reverse(config('genealabs-laravel-imagery.size-presets')) as $sizePreset) {
            $image = (new Imagery)->conjure(
                $this->source,
                $sizePreset,
                $sizePreset,
                [],
                ['doNotCreateDerivativeImages' => true]
            );

            if ($sizePreset < $this->width || $sizePreset < $this->height) {
                $sources .= "<source srcset=\"{$image->url}\" media=\"(min-width: {$sizePreset}px)\">";
            }
        }

        return "
            <picture>
                {$sources}
                <img src=\"{$this->url}\">
            </picture>
            <script src=\"{$scriptUrl}\"></script>
        ";
    }

    public function getUrlAttribute() : string
    {
        return asset(config('genealabs-laravel-imagery.storage-folder') . $this->fileName);
    }

    protected function createChacheFolderIfMissing()
    {
        app('filesystem')->disk('public')->makeDirectory(config('genealabs-laravel-imagery.storage-folder'));

        if (! file_exists(public_path(config('genealabs-laravel-imagery.storage-folder')))) {
            symlink(
                rtrim(storage_path('app/public/' . config('genealabs-laravel-imagery.storage-folder')), '/'),
                rtrim(public_path(config('genealabs-laravel-imagery.storage-folder')), '/')
            );
        }
    }
}

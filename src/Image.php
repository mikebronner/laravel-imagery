<?php namespace GeneaLabs\LaravelImagery;

use GeneaLabs\LaravelImagery\Jobs\RenderDerivativeImages;
use Intervention\Image\ImageManager;
use Jenssegers\Model\Model;
use Illuminate\Support\Collection;

class Image extends Model
{
    protected $originalHeight;
    protected $originalWidth;
    // protected $htmlAttributes;
    protected $heightIsPercentage;
    protected $widthIsPercentage;
    // protected $source;
    // protected $height;
    // protected $width;
    // protected $originalPath;
    protected $alwaysPreserveAspectRatio;
    protected $doNotCreateDerivativeImages;
    protected $overrideScreenConstraint;
    protected $screenConstraintMethod;

    //TODO: this class needs serious refactoring!!!
    public function __construct(
        string $source,
        string $width = null,
        string $height = null,
        Collection $htmlAttributes = null,
        Collection $options = null
    ) {
        parent::__construct();

        $this->createCacheFolderIfMissing();

        $this->originalHeight = $height;
        $this->originalWidth = $width;
        $this->htmlAttributes = $htmlAttributes;
        $this->heightIsPercentage = str_contains($height, '%');
        $this->widthIsPercentage = str_contains($width, '%');
        $this->source = $source;
        $this->image = (new ImageManager)->make($source);
        $this->height = intval($height);
        $this->width = intval($width);
        $this->originalPath = public_path(config('genealabs-laravel-imagery.storage-folder') . basename($source));
        $this->alwaysPreserveAspectRatio = $options->get('alwaysPreserveAspectRatio', true);
        $this->doNotCreateDerivativeImages = $options->get('doNotCreateDerivativeImages', false);
        $this->overrideScreenConstraint = $options->get('overrideScreenConstraint', false);
        $this->screenConstraintMethod = $options->get('screenConstraintMethod', 'contain');

        if ($this->sourceIsUrl($source)) {
            $this->image->save($this->originalPath);
        }

        $this->resizeImage($this->width, $this->height, $this->alwaysPreserveAspectRatio);

        if (! $this->doNotCreateDerivativeImages) {
            $job = (new RenderDerivativeImages($this->source))->onQueue('imagery');
            dispatch($job);
        }
    }

    protected function resizeImage(int $width, int $height, bool $alwaysPreserveAspect = false)
    {
        if (! $height || ! $width) {
            $height = $height ?: $this->image->getHeight();
            $width = $width ?: $this->image->getWidth();
        }

        $screenHeight = $_COOKIE['screenWidth'] ?? null;
        $screenWidth = $_COOKIE['screenHeight'] ?? null;
        $screenHeight = $screenWidth ? intval($screenWidth) : null;
        $screenWidth = $screenWidth ? intval($screenWidth) : null;
        $height = $this->determineHeight($height, $screenHeight);
        $width = $this->determineWidth($width, $screenWidth);

        if (! $height && ! $width) {
            $height = $this->image->height();
            $width = $this->image->width();
        }

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

    //TODO: refactor to have a single return type, instead of null or int
    protected function determineMaxHeight($height, $screenHeight, $screenWidth)
    {
        if (! $screenHeight || ! $screenWidth) {
            return $height;
        }

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

    //TODO: refactor to have a single return type, instead of null or int
    protected function determineMaxWidth($width, $screenHeight, $screenWidth)
    {
        if (! $screenHeight || ! $screenWidth) {
            return $width;
        }

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

        return $maxWidth;
    }

    protected function determineHeight($height, $screenHeight) : int
    {
        if ($screenHeight && $height && $this->heightIsPercentage) {
            return $screenHeight * ($height / 100);
        }

        return $height;
    }

    protected function determineWidth($width, $screenWidth) : int
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
        $this->image
            ->save(public_path(config('genealabs-laravel-imagery.storage-folder') . $this->fileName));
    }

    public function getFileNameAttribute() : string
    {
        $pathParts = pathinfo($this->source);
        $fileName = $pathParts['filename'];
        $extension = $pathParts['extension'] ?? '';
        $extension = $extension ? ".{$extension}" : '';

        if ($this->width || $this->height) {
            $fileName .= "_{$this->width}x{$this->height}";
        }

        return "{$fileName}{$extension}";
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
        return asset(config('genealabs-laravel-imagery.storage-folder') . $this->fileName);
    }

    public function getPathAttribute() : string
    {
        return public_path(config('genealabs-laravel-imagery.storage-folder') . $this->fileName);
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

    protected function createCacheFolderIfMissing()
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

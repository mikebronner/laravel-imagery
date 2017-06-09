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

    protected function resizeImage(int $width = null, int $height = null, bool $alwaysPreserveAspectRatio = null)
    {
        $screenHeight = $_COOKIE['screenHeight'];
        $screenWidth = $_COOKIE['screenWidth'];

        if ($screenHeight && $height && $this->heightIsPercentage) {
            $height = $screenHeight * ($height / 100);
        }

        if ($screenWidth && $width && $this->widthIsPercentage) {
            $width = $screenWidth * ($width / 100);
        }

        $maxHeight = $height ?: $this->image->height();
        $maxWidth = $width ?: $this->image->width();
        //TODO: figure out how to access unencrypted cookies using Laravel

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
            if ($alwaysPreserveAspectRatio || ! $this->image->width() || ! $this->image->height()) {
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
            $image = ((new Imagery)->conjure($this->source, $sizePreset, $sizePreset, [], ['doNotCreateDerivativeImages' => true]));

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

<?php namespace GeneaLabs\LaravelImagery;

class Imagery
{
    public function conjure(
        string $source,
        string $width = null,
        string $height = null,
        array $htmlAttributes = [],
        array $options = []
    ) : Image {
        $options = collect($options);
        $htmlAttributes = collect($htmlAttributes);
        $keyElements = collect([
            $source,
            $width,
            $height,
            $_COOKIE['screenWidth'] ?? '',
            $_COOKIE['screenHeight'] ?? '',
            $options->get('alwaysPreserveAspectRatio', true),
            $options->get('overrideScreenConstraint', false),
            $options->get('screenConstraintMethod', 'contain'),
        ])->concat($htmlAttributes)->toArray();

        return  cache()->remember(
            implode('-', $keyElements),
            9999999999,
            function () use ($source, $width, $height, $htmlAttributes, $options) {
                return new Image($source, $width, $height, $htmlAttributes, $options);
            }
        );
    }
}

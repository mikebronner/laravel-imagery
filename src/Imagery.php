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
        $keyElements = [
            $source,
            $width,
            $height,
            $options->get('alwaysPreserveAspectRatio', true),
            $options->get('overrideScreenConstraint', false),
            $options->get('screenConstraintMethod', 'contain'),
        ];

        return  cache()->remember(
            implode('-', $keyElements),
            9999999999,
            function () use ($source, $width, $height, $htmlAttributes, $options) {
                return new Image($source, $width, $height, $htmlAttributes, $options);
            }
        );
    }
}

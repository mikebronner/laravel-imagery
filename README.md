# Imagery for Laravel

**NOTE: Still under initial development, not yet functional. Initial release
 will be available in the next week or so. Stay tuned. :)**

## Goal
Provide developers a simplified solution to serving dynamic and optimized images,
 regardless if the user uploaded unoptimized images. This is particularly
 important for blogs and CMS'.

## Reasoning
There are some online third-party services out there that can handle this. But
 what do you do if you don't want the extra expense or complexity, or are
 serving sensitive images in an intranet setting that cannot be trusted to 3rd-
 party vendors? This package aims to fill that gap, and more-over, improve user
 experience in your application by loading assets faster, and at the right sizes.

## Planned Features
- Detects visitor's screen dimensions and optimizes the image for it.
- Your images don't have to be in a public folder to be served, imagery creates
 derivative images in its own public folder.
- Further optimizes derivative images through lossless compression (using async
 queue so as not to hold things up).

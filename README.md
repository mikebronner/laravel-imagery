# Imagery for Laravel

## Goal
Provide developers a simplified solution to serving dynamic and optimized images,
 regardless if the user uploaded unoptimized images. This is particularly
 important for blogs and CMS'.

## Reasoning
There are some online third-party services out there that can handle this. But
 what do you do if you don't want the extra expense or complexity, or are
 serving sensitive images in an intranet setting that cannot be trusted to 3rd-
 party vendors? This package aims to fill that gap, and more-over, improve user
 experience in your application be loading assets faster, and at the right sizes.

## Features
- Detects visitor's screen dimensions and optimizes the image for it.
- Your images don't have to be in a public folder to be served, imagery creates
 derivative images in its own public folder.

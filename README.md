Blade CLI
=========

Blade CLI is a command line compiler for the Laravel Blade templating engine. You simply specify what templates you want to process as arguments.

## Installation
To install you can either clone this repo and run `composer install && composer build` or simply retrieving the latest PHAR from the [releases page](https://www.github.com/nsrosenqvist/blade-cli/releases/latest).

## Usage
`compile [options] [--] <template> (<template>)...`

### Arguments
Argument     | Details                                      
-------------|----------------------------------------------
`<template>` | The template path can be specified as a relative URI, absolute and also as how Blade natively handles include references (pages/index.blade.php vs pages.index). If supplied as a Blade reference then a base directory must be set |

### Options:
Option        | Details                                    
--------------|--------------------------------------------
`--data=DATA` | Variables passed on to the template as a JSON file/string or a PHP file returning an associative array (multiple values allowed)
`--output-dir=OUTPUT-DIR` | Output path relative from current working directory or absolute
`--base-dir=BASE-DIR` | Base directory to look for template files from. If not set, template's containing dir is assumed (multiple values allowed)
`--output-ext=OUTPUT-EXT` | When an output dir is specified you can also set what file extension the compiled template should be created with [default: "txt"]
`--dynamic-base` | Automatically add the parent directories of all templates as base directories. This requires a new Blade compiler instance for each template file which adds overhead but simplifies processing multiple templates at once and have each be a self-contained template hierarchy tree. This is not compatible with templates supplied as native Blade references
`-h, --help` | Display this help message
`-q, --quiet` | Do not output any message
`-V, --version` | Display this application version
`--ansi` | Force ANSI output
`--no-ansi` | Disable ANSI output
`-n, --no-interaction` | Do not ask any interactive question
`-v/vv/vvv, --verbose` | Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

## Development
Clone and run `composer install`. You can then run the app by executing `php bin/blade` from the root directory. To create a distributable PHAR you run `composer build`. To verify that templates are processed correctly you can run `composer test`.

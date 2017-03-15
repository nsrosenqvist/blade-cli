<?php namespace NSRosenqvist\Blade\Console\Command;

// Console
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

// Blade
use InvalidArgumentException;
use NSRosenqvist\Blade\Compiler;

class Compile extends Command
{
    protected $name = 'compile';
    protected $description = 'Compiles a single or multiple Laravel Blade templates';

    protected function configure()
    {
        $this->setName($this->name);
        $this->setDescription($this->description);

        $this->addArgument(
            'template',
            InputArgument::REQUIRED | InputArgument::IS_ARRAY,
            'The template path can be specified as a relative URI, absolute and also as how Blade natively handles include references (pages/index.blade.php vs pages.index). If supplied as a Blade reference then a base directory must be set'
        );

        $this->addOption(
            'data',
            null,
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Variables passed on to the template as a JSON file/string or a PHP file returning an associative array'
        );

        $this->addOption(
            'output-dir',
            null,
            InputOption::VALUE_REQUIRED,
            'Output path relative from current working directory or absolute'
        );

        $this->addOption(
            'base-dir',
            null,
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Base directory to look for template files from. If not set, template\'s containing dir is assumed'
        );

        $this->addOption(
            'output-ext',
            null,
            InputOption::VALUE_REQUIRED,
            'When an output dir is specified you can also set what file extension the compiled template should be created with',
            'txt'
        );

        $this->addOption(
            'extend',
            null,
            InputOption::VALUE_REQUIRED,
            'This option accepts a path to a PHP file with user code to extend the compiler by using $compiler->extend()'
        );

        $this->addOption(
            'dynamic-base',
            null,
            InputOption::VALUE_NONE,
            'Automatically add the parent directories of all templates as base directories. This requires a new Blade compiler instance for each template file which adds overhead but simplifies processing multiple templates at once and have each be a self-contained template hierarchy tree. This is not compatible with templates supplied as native Blade references'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $templates = $input->getArgument('template');
        $dataPaths = $input->getOption('data');
        $outputDir = $input->getOption('output-dir');
        $outputExt = $input->getOption('output-ext');
        $extend = $input->getOption('extend');
        $baseDirs = $input->getOption('base-dir');
        $dynamicBase = $input->getOption('dynamic-base');

        // Validate multi file mode
        if (count($templates) > 1) {
            // Make sure that an outputDir is set if we're processing multiple templates
            if ( ! $outputDir) {
                throw new InvalidArgumentException("If you specify more than one template to compile you must also set a target directory with --output-dir");
            }
            // Make sure we have at least one base directory
            if (empty($baseDirs) && ! $dynamicBase) {
                $baseDirs[] = getcwd();
            }
        }
        else {
            // Validate single file mode

            // If no base dir is set we try and get it from the template path
            if (empty($baseDirs)) {
                $reference = $this->normalizeReference($templates[0], $baseDirs);

                // Try and get it from the path
                if (file_exists($reference)) {
                    $baseDirs[] = dirname($reference);
                }
                else {
                    // If we can't get it from the path and no base dir is set,
                    // then most likely the template would be residing in the current
                    // working directory, so let's use that one.
                    $baseDirs[] = getcwd();

                    // If the reference points to a template in a subdir we must add
                    // that one too
                    if (strpos($reference, '.') !== false) {
                        $directory = dirname(str_replace('.', DIRECTORY_SEPARATOR, $reference));
                        $directory = getcwd().DIRECTORY_SEPARATOR.$directory;

                        $baseDirs[] = $directory;
                    }
                }
            }
        }

        // Load data file
        $data = [];

        foreach ($dataPaths as $dataPath) {
            $data = array_merge($data, $this->loadData($dataPath));
        }

        // Create compiler
        $cacheDir = sys_get_temp_dir().'/blade/views';
        $blade = new Compiler($cacheDir, $baseDirs);

        if (file_exists($extend)) {
            includeExtensions($blade, $extend);
        }

        // Loop through all templates
        foreach ($templates as $template) {
            // Compile template
            $reference = $this->normalizeReference($template, $baseDirs);

            // If not using dynamic base, use the global compiler
            if ( ! $dynamicBase) {
                $compiled = $blade->render($reference, $data);
            }
            else {
                // If using a dynamic base, create a new compiler instance for
                // each template we're compiling
                $dynamicBase = dirname($reference);
                $dynamicBase = ($dynamicBase == '.') ? getcwd() : $dynamicBase;

                $dynamicDirs = array_merge($baseDirs, [$dynamicBase]);
                $bladeDyn = new Compiler($cacheDir, $dynamicDirs);

                if (file_exists($extend)) {
                    includeExtensions($bladeDyn, $extend);
                }

                $compiled = $blade->render($reference, $data);
            }

            // Write file
            if ($outputDir) {
                // If it's a template reference we convert it into a filesystem URI
                // so that sub-templates with the same name don't overwrite each other
                if ( ! file_exists($reference)) {
                    $uri = str_replace('.', '/', $template);
                    $directory = $outputDir.DIRECTORY_SEPARATOR.dirname($uri);
                    $filename = basename($uri);

                    $path = $this->outputPath($directory, $filename, $outputExt);
                }
                else {
                    // Make sure we keep the file hierarchy
                    $directory = $outputDir;

                    if (dirname($template) !== '.') {
                        $directory .= DIRECTORY_SEPARATOR.dirname($template);
                    }

                    $path = $this->outputPath($directory, $reference, $outputExt);
                }

                // output to file
                $this->writeFile($path, $compiled);
            }
            else {
                // Output to stdOut (this should only be possible if we're
                // processing only one template since we're throwing an exception
                // when $outputDir isn't set in multi file mode)
                $output->writeln($compiled);
            }
        }
    }

    protected function strEndsWith($haystack, $needle) {
        $length = strlen($needle);

        if ($length == 0) {
            return true;
        }

        return (substr($haystack, -$length) === $needle);
    }

    protected function strStartsWith($haystack, $needle) {
         $length = strlen($needle);
         return (substr($haystack, 0, $length) === $needle);
    }

    protected function outputPath($directory, $template, $extension) {
        // Remove original file extension
        $filename = pathinfo($template, PATHINFO_FILENAME);

        // Remove .blade as well
        if ($this->strEndsWith($filename, '.blade')) {
            $filename = pathinfo($filename, PATHINFO_FILENAME);
        }

        // Build path
        $basename = $filename.'.'.$extension;
        $path = $directory.DIRECTORY_SEPARATOR.$basename;

        return $path;
    }

    protected function normalizeReference($template, array $baseDirs = []) {
        // If it's a real file name and not a template name (pages/index.blade.php vs pages.index)
        // It must be converted into an absolute path for the compiler to process it correctly
        // due to the nature of how shell scripts usually processes file input from the CLI

        // Is it a direct reference?
        if (file_exists($template)) {
            return realpath($template);
        }

        // Is it a relative path within one of the base dirs?
        foreach ($baseDirs as $base) {
            $path = $base.DIRECTORY_SEPARATOR.$template;

            if (file_exists($path)) {
                return realpath($path);
            }
        }

        // If nothing has been returned yet then it has to be a template name
        return $template;
    }

    protected function loadData($path = null)
    {
        if ($path) {
            // See if it's a real file or a JSON string
            if (file_exists($path)) {
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                // Include the file
                switch ($extension) {
                    case 'json': $data = json_decode(file_get_contents($path), true); break;
                    case 'php': $data = include $path; break;
                }
            }
            // Try parsing JSON string
            else {
                $data = json_decode($path, true);
            }
        }

        return $data ?? [];
    }

    protected function writeFile($path, $content) {
        // If the directory for the file doesn't exist we create it recursively
        if ( ! file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        // Write file to target directory
        if ( ! file_put_contents($path, $content)) {
            throw new ErrorException("Failed to write output to file: ".$path);
        }
    }
}

function includeExtensions(&$compiler, $path) {
    include $path;
}

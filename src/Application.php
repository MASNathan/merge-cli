<?php

namespace MASNathan\Merge;

use MASNathan\Parser\Parser;

class Application
{
    /**
     * @var string
     */
    protected $location;

    /**
     * @var array
     */
    protected $input = [];

    /**
     * @var string
     */
    protected $output;

    /**
     * Application constructor.
     * @param string $currentWorkingDirectory
     * @param array  $args
     * @throws \Exception
     */
    public function __construct(string $currentWorkingDirectory, array $args)
    {
        $this->parseInputArguments($args);
        $this->location = $currentWorkingDirectory;
    }

    /**
     * @param array $args
     * @throws \Exception
     */
    protected function parseInputArguments(array $args): void
    {
        array_shift($args);

        if (!in_array('-o', $args) && !in_array('--out', $args)) {
            throw new \Exception("No output file defined");
        }

        $line = implode(" ", $args);
        $line = str_replace([" -o ", " --out "], " --out ", $line);
        $line = explode(" --out ", $line);

        $this->input = explode(" ", $line[0]);
        $outputFiles = explode(" ", $line[1]);

        if (count($outputFiles) == 0) {
            throw new \Exception("No output file defined");
        }

        if (count($outputFiles) > 1) {
            throw new \Exception("You can only define one output file");
        }

        $this->output = $outputFiles[0];

        if (!is_writable($this->output)) {
            throw new \Exception("Can't write output file");
        }
    }

    public function run()
    {
        $mergedData = [];
        foreach ($this->input as $file) {
            $filePath = realpath($this->location . DIRECTORY_SEPARATOR . $file);

            if (!$filePath) {
                throw new \Exception("Couldn't find '$file'");
            }

            $data = Parser::file($filePath)
                ->from(pathinfo($filePath, PATHINFO_EXTENSION))
                ->toArray();

            $mergedData = $this->mergeRecursive($mergedData, $data);
        }

        $outputExtension = pathinfo($this->output, PATHINFO_EXTENSION);

        switch ($outputExtension) {
            case 'json':
                $content = json_encode($mergedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                break;

            default:
                $content = Parser::data($mergedData)
                    ->setPrettyOutput(true)
                    ->to($outputExtension);
                break;
        }

        file_put_contents($this->location . DIRECTORY_SEPARATOR . $this->output, $content);
    }

    protected function mergeRecursive(array & $array1, array & $array2): array
    {
        $merged = $array1;

        foreach ($array2 as $key => & $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->mergeRecursive($merged[$key], $value);
            } else {
                if (is_numeric($key)) {
                    if (!in_array($value, $merged)) {
                        $merged[] = $value;
                    }
                } else {
                    $merged[$key] = $value;
                }
            }
        }

        return $merged;
    }
}


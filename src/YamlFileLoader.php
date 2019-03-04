<?php
declare(strict_types=1);

namespace Miraizou\Yaml;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Yaml;
use function JmesPath\search;

class YamlFileLoader extends Loader
{

    /**
     * Loads a resource.
     *
     * @param mixed $resource The resource
     * @param string|null $type The resource type or null if unknown
     * @param YamlParser $parser optional parser instance to use (for e.g. included sub-documents)
     *
     * @return mixed
     *
     * @throws \Exception If something went wrong
     * @throws FileNotFoundException
     */
    public function load($resource, $type = null, YamlParser $parser = null)
    {
        $resourcePath = \realpath($resource);
        if (!\is_file($resourcePath) || !\is_readable($resourcePath)) {
            throw new FileNotFoundException(null, 0, null, $resource);
        }

        $basepath = \dirname($resourcePath);

        $parser = $parser ?? new YamlParser();
        $configValues = $parser->parseFile($resourcePath, Yaml::PARSE_CUSTOM_TAGS|YamlParser::PARSE_KEEP_REFS);

        \array_walk_recursive($configValues, function(&$item/*, $key*/) use ($parser, $basepath) {
            if ($item instanceof TaggedValue && $item->getTag() === 'inc/file') {
                $lines = \preg_split("/\n/",$item->getValue(), -1, PREG_SPLIT_NO_EMPTY);

                $items = [];
                foreach($lines as $line) {
                    [$file,$pointer] = \explode('#', $line, 2);
                    if ($file !== '') {
                        $filepath = $file;
                        if (\preg_match('`^(?:\w+:/)?/`', $file) !== 1) {
                            $filepath = $basepath . DIRECTORY_SEPARATOR . $file;
                        }

                        try {
                            $includeFiles = glob($filepath) ?: [];
                            foreach($includeFiles as $f) {
                                $innerConfig = $this->load($f, null, $parser);

                                if ($pointer === null) {
                                    $items[] = $innerConfig;
                                } else {
                                    $items[] = search($pointer, $innerConfig);
                                }
                            }
                        } catch (FileNotFoundException $e) {
                            throw $e;
                        }
                    }
                }

                // TODO does this work in all cases?
                if (count($items) > 0) {
                    $item = \array_merge(...$items);
                } else {
                    $item = null;
                }
            }
        });

        return $configValues;
    }

    /**
     * Returns whether this class supports the given resource.
     *
     * @param mixed $resource A resource
     * @param string|null $type The resource type or null if unknown
     *
     * @return bool True if this class supports the given resource, false otherwise
     */
    public function supports($resource, $type = null) : bool {
        return \is_string($resource) && \in_array(\pathinfo(
            $resource,
            PATHINFO_EXTENSION
        ), ['yaml','yml']);
    }
}
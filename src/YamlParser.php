<?php
declare(strict_types=1);

namespace Miraizou\Yaml;

use Miraizou\Core\ModifyParentPrivates;
use ReflectionException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;

class YamlParser extends Parser
{
    use ModifyParentPrivates;

    public const PARSE_KEEP_REFS = 65536;

    /**
     * Parses a YAML file into a PHP value.
     *
     * @param string $filename The path to the YAML file to be parsed
     * @param int    $flags    A bit field of PARSE_* constants to customize the YAML parser behavior
     *
     * @return mixed The YAML converted to a PHP value
     *
     * @throws ParseException If the file could not be read or the YAML is not valid
     *
     * @see Parser::parseFile()
     */
    public function parseFile(string $filename, int $flags = 0)
    {
        try {
            if (!is_file($filename)) {
                throw new ParseException(sprintf('File "%s" does not exist.', $filename));
            }

            if (!is_readable($filename)) {
                throw new ParseException(sprintf('File "%s" cannot be read.', $filename));
            }


            try {
                return $this->parse(file_get_contents($filename), $flags);
            } finally {
                $this->setParentPrivateProperty('filename', null);
            }
        } catch (ReflectionException $e) {
            return parent::parseFile($filename, $flags);
        }
    }

    /**
     * Parses a YAML string to a PHP value.
     *
     * @param string $value A YAML string
     * @param int    $flags A bit field of PARSE_* constants to customize the YAML parser behavior
     *
     * @return mixed A PHP value
     *
     * @throws ParseException If the YAML is not valid
     *
     * @see Parser::parse()
     */
    public function parse(string $value, int $flags = 0)
    {
        try {
            if (false === preg_match('//u', $value)) {
                throw new ParseException('The YAML value does not appear to be valid UTF-8.', -1, null, $this->getParentPrivateProperty('filename'));
            }

            if (!(self::PARSE_KEEP_REFS & $flags)) {
                $this->setParentPrivateProperty('refs', []);
            }

            $mbEncoding = null;
            $data = null;

            if (2 /* MB_OVERLOAD_STRING */ & (int) ini_get('mbstring.func_overload')) {
                $mbEncoding = mb_internal_encoding();
                mb_internal_encoding('UTF-8');
            }

            try {
                $data = $this->invokeParentPrivateMethod('doParse', $value, $flags);
            } finally {
                if (null !== $mbEncoding) {
                    mb_internal_encoding($mbEncoding);
                }
                $this->setParentPrivateProperty('lines', [])
                    ->setParentPrivateProperty('currentLine', '');
                if (!(self::PARSE_KEEP_REFS & $flags)) {
                    $this->setParentPrivateProperty('refs', []);
                }
                $this->setParentPrivateProperty('skippedLineNumbers', [])
                    ->setParentPrivateProperty('locallySkippedLineNumbers', []);
            }

            return $data;
        } catch (ReflectionException $e) {
            return parent::parse($value, $flags);
        }
    }
}

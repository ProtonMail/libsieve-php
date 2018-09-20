<?php

use PHPUnit\Framework\TestCase;
use Sieve\SieveParser;

final class SieveParserTest extends TestCase
{
    public function testTest()
    {
        $parser = new SieveParser();
        static::assertTrue(true);
    }

    private function provider($dir_name)
    {
        $sieves = array();
        $directory_iterator = new DirectoryIterator(__DIR__ . '/' . $dir_name);
        $iterator = new RegexIterator($directory_iterator, "/.*.siv/", RegexIterator::MATCH);
        foreach ($iterator as $sieve_file) {
            $sieves[$sieve_file->getBasename('.siv')] = array(file_get_contents($sieve_file->getPathname()));
        }

        return $sieves;
    }

    public function goodProvider()
    {
        return $this->provider("good");
    }

    public function badProvider()
    {
        return $this->provider("bad");
    }

    /**
     * @dataProvider goodProvider
     */
    public function testGood($sieve)
    {
        $parser = new SieveParser();
        $parser->parse($sieve);
        // it should not raise an exception
        static::assertTrue(true);
    }

    /**
     * @dataProvider badProvider
     * @expectedException \Sieve\SieveException
     */
    public function testBad($sieve)
    {
        $parser = new SieveParser();
        $parser->parse($sieve);
    }
}



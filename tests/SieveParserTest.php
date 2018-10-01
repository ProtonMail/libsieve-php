<?php

use PHPUnit\Framework\TestCase;
use Sieve\SieveParser;

final class SieveParserTest extends TestCase
{
    public function testWhiteSpacesArePreserved()
    {
        $sieve = <<<EOS
require "vacation";
require ["vacation", "relational"];

# I don't want this comment to be removed.

if header :value     /* ignored */  "lt" ["x-priority"] ["3"]
{
    /* the priority is low */

    stop
    ;
}

vacation :mime 
"Content-Type: text/html
I'm out of office, please contact Joan Doe instead.
Best regards
stop;
John Doe";


vacation "stop;";stop;
EOS;
        $parser = new SieveParser();
        $parser->parse($sieve);;

        static::assertEquals($sieve, $parser->GetParseTree()->GetText());
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

    private function provider($dir_name)
    {
        $directory_iterator = new DirectoryIterator(__DIR__ . '/' . $dir_name);
        $iterator = new RegexIterator($directory_iterator, "/.*.siv/", RegexIterator::MATCH);
        foreach ($iterator as $sieve_file) {
            yield $sieve_file->getBasename('.siv') => [file_get_contents($sieve_file->getPathname())];
        }
    }

    public function goodProvider()
    {
        return $this->provider("good");
    }

    public function badProvider()
    {
        return $this->provider("bad");
    }
}



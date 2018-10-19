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
        $parser->parse($sieve);

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

    /**
     * @dataProvider dataDumpProvider
     * @param string $sieve
     * @param string $dump_expected
     * @throws \Sieve\SieveException
     */
    public function testDump(string $sieve, string $dump_expected)
    {
        $parser = new SieveParser();
        $parser->parse($sieve);
        $dump = $parser->dumpParseTree();

        $this->assertStringStartsWith(
            str_replace(['"', '<', '>'], '', trim($dump_expected)),
            str_replace(['"', '<', '>'], '', trim($dump))
        );
    }

    public function dataDumpProvider()
    {
        $dump_expected0 = <<<DUMP
tree
 `--- <if> type:identifier line:1 (id:1)
    |--- < > type:whitespace line:1 (id:2)
    |--- <allof> type:identifier line:1 (id:3)
    |  |--- <(> type:left parenthesis line:1 (id:4)
    |  |--- <true> type:identifier line:1 (id:5)
DUMP;

        $dump_expected1 = <<<DUMP
tree
 `--- <if> type:identifier line:1 (id:1)
    |--- < > type:whitespace line:1 (id:2)
    |--- <header> type:identifier line:1 (id:3)
    |  |--- < > type:whitespace line:1 (id:4)
    |  |--- <:is> type:tag line:1 (id:5)
    |  |  `--- < > type:whitespace line:1 (id:6)
    |  |--- <"To"> type:quoted string line:1 (id:7)
    |  |  `--- < > type:whitespace line:1 (id:8)
    |  |--- <[> type:left bracket line:1 (id:9)
    |  |--- <"a@b.c"> type:quoted string line:1 (id:10)
    |  `--- <]> type:right bracket line:1 (id:11)
    |     `--- < > type:whitespace line:1 (id:12)
    |--- <{> type:block start line:1 (id:13)
    |  `--- < > type:whitespace line:1 (id:14)
    |--- <keep> type:identifier line:1 (id:15)
    |  `--- <;> type:semicolon line:1 (id:16)
    |     `--- < > type:whitespace line:1 (id:17)
    `--- <}> type:block end line:1 (id:18)
DUMP;

        $dump_expected2 = <<<DUMP
tree
 `--- <require> type:identifier line:1 (id:1)
    |--- < > type:whitespace line:1 (id:2)
    |--- <[> type:left bracket line:1 (id:3)
    |--- <"virustest"> type:quoted string line:1 (id:4)
DUMP;

        yield ['if allof(true) {stop;}', $dump_expected0];
        yield ['if header :is "To" ["a@b.c"] { keep; }', $dump_expected1];
        yield [
            'require ["virustest"
            , "relational"
            , "comparator-i;ascii-numeric"
            ];',
            $dump_expected2
        ];
    }
}



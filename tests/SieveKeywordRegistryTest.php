<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sieve\SieveParser;

class SieveKeywordRegistryTest extends TestCase
{
    /**
     * Checks that the extension cannot be loaded if it forbids usage of another already loaded extension.
     */
    public function testForbiddenExtensionLoadedBefore()
    {
        $path = __DIR__ . "/customExtensions/requireForbidsExtension.xml";
        $parser = new SieveParser(null, [$path]);
        $sieve = <<<EOS
require ["spamtest", "forbidden"];
EOS;

        try {
            $parser->parse($sieve);
        } catch (\Sieve\SieveException $e) {
            $this->assertSame(1, $e->getLineNo());

            $this->expectException(\Sieve\SieveException::class);
            throw $e;
        }
    }

    /**
     * Checks extension that require another extension.
     *
     * @throws \Sieve\SieveException
     */
    public function testRequiresExtension(): void
    {
        $path = __DIR__ . "/customExtensions/requireRequiresExtension.xml";
        $parser = new SieveParser(null, [$path]);
        $sieve = <<<EOS
require ["spamtest", "requires"];
EOS;

        $parser->parse($sieve);
        static::assertTrue(true);

        $parser = new SieveParser(null, [$path]);
        $sieve = <<<EOS
require ["requires", "spamtest"];
EOS;

        // works in both order
        $parser->parse($sieve);
        static::assertTrue(true);
    }

    /**
     * Checks extensions fails if required extension is not loaded before.
     *
     * @throws \Sieve\SieveException
     */
    public function testRequiresExtensionFailsIfNotLoaded(): void
    {
        $path = __DIR__ . "/customExtensions/requireRequiresExtension.xml";
        $parser = new SieveParser(null, [$path]);
        $sieve = <<<EOS
require ["requires"];
EOS;

        $this->expectException(\Sieve\SieveException::class);

        $parser->parse($sieve);
    }

    /**
     * Checks the behavior when several extensions are required.
     * @throws \Sieve\SieveException
     * @dataProvider mixExtensionProvider
     */
    public function testMixExtension(string $sieveExtensions, ?string $exception = null): void
    {
        $path = __DIR__ . "/customExtensions/requireMixedExtension.xml";
        $parser = new SieveParser(null, [$path]);

        $sieve = <<<EOS
require [$sieveExtensions];
EOS;

        if (isset($exception)) {
            static::expectException($exception);
        }

        $parser->parse($sieve);
        static::assertTrue(true);
    }

    /**
     * Provides various extensions requirement configuration.
     */
    public function mixExtensionProvider(): array
    {
        return [
            "correct" => [
                '"relational", "mixed"',
            ],
            "correct inversed" => [
                '"mixed", "relational"',
            ],
            "missing required" => [
                '"mixed"',
                \Sieve\SieveException::class,
            ],
            "one forbidden extension" => [
                '"mixed", "spamtest"',
                \Sieve\SieveException::class,
            ],
            "several forbidden extensions" => [
                '"vacation", "mixed", "spamtest"',
                \Sieve\SieveException::class,
            ],
        ];
    }

    public function testWrongExtensionType(): void
    {
        $path = __DIR__ . "/customExtensions/wrongTypeExtension.xml";
        $parser = new SieveParser(null, [$path]);
        $sieve = <<<EOS
require ["wrong"];
EOS;

        $this->expectException(\Sieve\SieveException::class);
        $this->expectExceptionMessage('Unsupported extension type \'silly\' in extension \'wrong\'');

        $parser->parse($sieve);
    }
}

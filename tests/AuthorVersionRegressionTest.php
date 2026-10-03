<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/** Run the isolated endpoint/UI regressions without polluting the PKP runtime. */
class AuthorVersionRegressionTest extends TestCase
{
    public function testEndpointStates(): void
    {
        $process = new Process([PHP_BINARY, __DIR__ . '/api/author-version-handler.php']);
        $process->run();
        self::assertTrue($process->isSuccessful(), $process->getOutput() . $process->getErrorOutput());
    }

    public function testWorkflowControls(): void
    {
        $process = new Process(['node', __DIR__ . '/js/authorVersion.test.cjs']);
        $process->run();
        self::assertTrue($process->isSuccessful(), $process->getOutput() . $process->getErrorOutput());
    }
}

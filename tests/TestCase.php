<?php

declare(strict_types=1);

namespace IntrAgile\TextExtractor\Laravel\Tests;

use IntrAgile\TextExtractor\Laravel\Facades\TextExtractor;
use IntrAgile\TextExtractor\Laravel\TextExtractorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [TextExtractorServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['TextExtractor' => TextExtractor::class];
    }
}

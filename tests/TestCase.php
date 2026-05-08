<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithCachedConfig;

abstract class TestCase extends BaseTestCase
{
    use WithCachedConfig;
}

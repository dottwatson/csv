<?php

declare(strict_types=1);

namespace dottwatson\csv;

use PHPUnit\Framework\TestCase;

class csvTest extends TestCase
{
    /**
     * @var csv
     */
    protected $csv;

    protected function setUp() : void
    {
        $this->csv = new csv;
    }

    public function testIsInstanceOfcsv() : void
    {
        $actual = $this->csv;
        $this->assertInstanceOf(csv::class, $actual);
    }
}

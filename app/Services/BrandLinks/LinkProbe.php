<?php

namespace App\Services\BrandLinks;

interface LinkProbe
{
    public function probe(string $url): ProbeResult;
}

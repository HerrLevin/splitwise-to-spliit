<?php

namespace App\Importer;

class SpliitImporter
{
    private string $baseUrl;
    private string $groupId;
    private CsvParser $csvParser;

    public function __construct(string $baseUrl, string $groupId, CsvParser $parser)
    {
        $this->baseUrl = $baseUrl;
        $this->groupId = $groupId;
        $this->csvParser = $parser;
    }

}

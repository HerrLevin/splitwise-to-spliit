<?php

namespace App\Importer;

use RuntimeException;

class CsvParser
{
    private string $csvFilePath;
    private array $data = [];

    public function __construct(string $csvFilePath)
    {
        $this->csvFilePath = $csvFilePath;
        $this->parse();
    }

    public function getExepnseCount(): int
    {
        if (empty($this->data)) {
            $this->parse();
        }
        return count($this->data);
    }

    public function parse(): array
    {
        if (!empty($this->data)) {
            return $this->data; // Return cached data if already parsed
        }

        if (!file_exists($this->csvFilePath) || !is_readable($this->csvFilePath)) {
            throw new RuntimeException("CSV file not found or not readable: {$this->csvFilePath}");
        }

        $this->data = [];
        if (($handle = fopen($this->csvFilePath, 'r')) !== false) {
            $header = null;
            while (($row = fgetcsv($handle, 1000, ',', '"', '\\')) !== false) {
                if (!$header && $row[0] !== null && !str_contains($row[0], ':')) {
                    $header = $row;
                } elseif (!empty($header) && count($row) === count($header) && !str_contains($row[1], 'Total balance')) {
                    $this->data[] = array_combine($header, $row);
                }
            }
            fclose($handle);
        }

        return $this->data;
    }

    private function getHeader(): array
    {
        if (empty($this->data)) {
            $this->parse();
        }
        return !empty($this->data) ? array_keys($this->data[0]) : [];
    }

    public function getUsers(): array
    {
        $users = [];
        $header = $this->getHeader();

        $cnt = 0;
        foreach ($header as $row) {
            if ($cnt < 5) {
                $cnt++;
                continue;
            }
            $users[] = $row;
        }

        return $users;
    }
}

<?php

namespace App\Importer;

use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class SpliitImporter
{
    const string CATEGORY_SEPARATOR = ' - ';
    private string $baseUrl;
    private string $groupId;
    private CsvParser $csvParser;
    private Client $client;
    private array $categories = [];

    private array $userMapping = []; // key: splitwise name, value: spliit user-id

    public function __construct(string $baseUrl, string $groupId, CsvParser $parser)
    {
        $this->baseUrl = $baseUrl;
        $this->groupId = $groupId;
        $this->csvParser = $parser;

        $this->client = new Client();
    }

    public function setUserMapping(array $mapping): void
    {
        $this->userMapping = $mapping;
    }

    /**
     * @throws GuzzleException
     */
    public function getUsers(): array
    {
        $response = $this->client->request('GET', $this->baseUrl . '/api/trpc/groups.expenses.list,groups.get?batch=1&input={"0":{"json":{"groupId":"' . $this->baseUrl . '","limit":20,"filter":"","direction":"forward"}},"1":{"json":{"groupId":"' . $this->groupId . '"}}}');
        $body = $response->getBody();
        $json = json_decode($body, true);

        return $json[1]['result']['data']['json']['group']['participants'] ?? [];
    }

    private function getCategories(): array
    {
        if (!empty($this->categories)) {
            return $this->categories; // Return cached categories if already fetched
        }

        try {
            $response = $this->client->request('GET', $this->baseUrl . '/api/trpc/groups.get,categories.list?batch=1&input={"0":{"json":{"groupId":"' . $this->groupId . '"}},"1":{"json":null,"meta":{"values":["undefined"]}}}');
            $body = $response->getBody();
            $json = json_decode($body, true);

            $this->categories = $json[1]['result']['data']['json']['categories'] ?? [];
        } catch (GuzzleException $e) {
            // Handle exception (e.g., log the error)
            $this->categories = [];
        }

        return $this->categories;
    }

    private function getCategoryIdByName(string $name): ?string
    {
        if (str_contains($name, self::CATEGORY_SEPARATOR)) {
            $name = substr($name, 0, strpos($name, self::CATEGORY_SEPARATOR));
        }

        $categories = $this->getCategories();
        foreach ($categories as $category) {
            if (strcasecmp($category['name'], $name) === 0) {
                return $category['id'];
            }
        }

        if ($name !== 'General') {
            return $this->getCategoryIdByName('General');
        }

        return null; // Return null if no matching category is found
    }

    private function getUserIdByName(string $name): ?string
    {
        return $this->userMapping[$name] ?? null;
    }

    public function import(): Generator
    {
        $data = $this->csvParser->parse();
        foreach ($data as $row) {
            $rowData = $this->parseRow($row);

            yield $this->createExpense($rowData);
        }
    }

    public function createExpense(array $data): ?array
    {
        $test = ['json' => [
            'groupId' => $this->groupId,
            'expenseFormValues' => [
                'expenseDate' => $data['date'],
                'title' => $data['description'],
                'category' => $data['categoryId'],
                'amount' => $data['amount'],
                'conversionRate' => null,
                'paidBy' => $data['paidBy'],
                'paidFor' => $data['paidFor'],
                'splitMode' => 'BY_AMOUNT',
                'saveDefaultSplittingOptions' => false,
                'isReimbursement' => $data['isReimbursement'],
                'documents' => [],
                'notes' => "",
                'recurrenceRule' => 'NONE',
            ],
            'participantId' => $data['paidBy'],
        ],
            'meta' => [
                'values' => [
                    'expenseFormValues.expenseDate' => [
                        'Date'
                    ],
                    "expenseFormValues.conversionRate" => [
                        "undefined"
                    ],
                ]
            ]];

        $body = '{"0":' . json_encode($test) . '}';

        try {
            $response = $this->client->request('POST', $this->baseUrl . '/api/trpc/groups.expenses.create?batch=1', [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body' => $body
            ]);

            return $response->getStatusCode() === 200 ? $data : null;
        } catch (GuzzleException $e) {
            // Handle exception (e.g., log the error)
            var_dump($e->getMessage());
            var_dump($body);
            return null;
        }
    }

    /**
     * @param mixed $row
     * @return array
     */
    public function parseRow(mixed $row): array
    {
        $date = date('Y-m-d', strtotime($row['Date']));
        $amount = (int)str_replace('.', '', $row['Cost']);
        $description = $row['Description'] ?: 'Imported from Splitwise';
        $categoryId = (int)$this->getCategoryIdByName($row['Category'] ?: 'General');
        $isReimbursement = $row['Category'] === 'Payment';
        unset($row['Category'], $row['Date'], $row['Description'], $row['Amount']);

        $paidById = null;
        $owedByIds = [];
        foreach ($row as $userName => $value) {
            if (isset($this->userMapping[$userName]) && $value !== '' && (float)$value !== 0.0) {
                $userId = $this->getUserIdByName($userName);
                if ($userId !== null) {
                    $value = (int)str_replace('.', '', $value);
                    if ($value > 0) {
                        $paidById = $userId;
                    }

                    $value = $value > 0 ? $amount - $value : abs($value);

                    if ($value !== 0) {
                        $owedByIds[] = [
                            'participant' => $userId,
                            'shares' => $value,
                        ];
                    }
                }
            }
        }

        return [
            'date' => $date,
            'amount' => $amount,
            'description' => $description,
            'categoryId' => $categoryId,
            'paidBy' => $paidById,
            'paidFor' => $owedByIds,
            'isReimbursement' => $isReimbursement
        ];
    }
}

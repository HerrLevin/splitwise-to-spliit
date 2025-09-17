<?php
namespace App\Command;

use App\Importer\CsvParser;
use App\Importer\SpliitImporter;
use App\Importer\SpliitUrlParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class ImportCommand extends Command
{
    protected static $defaultName = 'import';
    protected static $defaultDescription = 'Guides you through the Splitwise to Spliit import process.';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Splitwise to Spliit Importer');

        // Step 1: Ask for Splitwise CSV file path
        $filePath = $io->ask('Please enter the path to your Splitwise CSV export file', 'export.csv');
        if (!file_exists($filePath)) {
            $io->error('File not found: ' . $filePath);
            return Command::FAILURE;
        }

        $csvParser = new CsvParser($filePath);
        $io->success('Parsed ' . $csvParser->getExepnseCount() . ' expenses from CSV file.');

        // Step 2: Ask for output file path
        $urlOrGroup = $io->ask('Please enter the spliit url or group-ID');
        $parser = new SpliitUrlParser($urlOrGroup);
        $baseUrl = $parser->getBaseUrl();
        $groupId = $parser->getGroupId();

        $io->success('Splitwise group-ID: ' . $groupId);
        $io->success('Spliit base URL: ' . $baseUrl);

        $importer = new SpliitImporter($baseUrl, $groupId, $csvParser);
        $spliitUsers = $importer->getUsers();
        $splitwiseUsers = $csvParser->getUsers();
        $userMapping = []; // key: splitwise name, value: spliit user-id

        // make user select mapping
        foreach ($splitwiseUsers as $swUser) {
            $io->section('Mapping for Splitwise user: ' . $swUser);
            $choices = [];
            foreach ($spliitUsers as $spUser) {
                $choices[$spUser['id']] = $spUser['name'];
            }
            $selected = $io->choice('Select the corresponding Spliit user:', $choices, null);
            $userMapping[$swUser] = $selected;
            $io->success('Mapped Splitwise user "' . $swUser . '" to Spliit user "' . $choices[$selected] . '"');
        }

        $importer->setUserMapping($userMapping);

        // Step 3: Confirm import
        if (!$io->confirm('Do you want to proceed with the import?', true)) {
            $io->warning('Import cancelled.');
            return Command::SUCCESS;
        }

        // Step 4: (Placeholder) Perform import logic
        $io->section('Importing...');
        $io->progressStart($csvParser->getExepnseCount());
        foreach ($importer->import() as $line) {
            if ($line) {
                $io->progressAdvance();
            } else {
                $io->error('Error importing line, see above for details.');
            }
        }
        $io->progressFinish();
        $io->success('Import completed!');
        return Command::SUCCESS;
    }
}


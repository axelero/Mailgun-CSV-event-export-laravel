<?php

use Illuminate\Console\Command;
use Mailgun\Mailgun;
use Symfony\Component\Console\Input\InputArgument;

class CsvMergeCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'csv:merge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'merge a file in the first one';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $first = $this->argument('first');
        $second = $this->argument('second');
        $dest = $this->argument('dest');

        $headers = $this->getHeaders($first);

        if (!file_exists($second)) {
            throw new InvalidArgumentException("File $second not found");
        }

        if (file_exists($dest)) {
            unlink($dest);
        }

        file_put_contents($dest, $this->strPutCsvHeaders($headers));
        $this->append($first, $headers, $dest);
        $this->append($second, $headers, $dest);

        $destFullPath = realpath($dest);
        if (!$dest) {
            throw new RuntimeException("Could not write to $dest");
        }

        $this->info('Files merged in <comment>' . $destFullPath . '</comment>');

    }

    protected function strPutCsvHeaders($data) {
        # Generate CSV data from array
        $fh = fopen('php://temp', 'rw'); # don't create a file, attempt
        # to use memory instead

        # write out the headers
        fputcsv($fh, $data);

        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return $csv;
    }
    public function append($from, $headers, $to)
    {
        $source = fopen($from, "r");
        $dest = fopen($to, "a+");
        $first = true;
        $keys = [];

        while (($record = fgetcsv($source, 0, ",")) !== false) {
            if ($first == true) {
                $keys = $record;
                $first = false;
                continue;
            }
            if (count($keys) != count($record)) {
                if ($record[1] == 'id') {
                    continue;
                }
                $this->error('count mismatch');
            }
            $record = array_combine($keys, $record);
            if ($record['id'] == 'id') {
                continue;
            }

            fputcsv($dest, $this->normalize($record, $headers));
        }
        fclose($source);
        fclose($dest);
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['first', InputArgument::REQUIRED],
            ['second', InputArgument::REQUIRED],
            ['dest', InputArgument::REQUIRED],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array();
    }

    protected function getHeaders($first)
    {
        $handle = fopen($first, "r");
        while (($data = fgetcsv($handle, 0, ",")) !== false) {
            fclose($handle);
            return $data;
        }
        fclose($handle);
        throw new InvalidArgumentException("File $first not found");
    }

    private function normalize($record, $headers)
    {
        $newRecord = [];
        foreach ($headers as $name) {
            $newRecord[$name] = !empty($record[$name]) ? $record[$name] : '';
        }

        return $newRecord;
    }
}

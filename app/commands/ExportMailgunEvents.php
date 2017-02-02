<?php

use Carbon\Carbon;
use Illuminate\Console\Command;
use Mailgun\Mailgun;

class ExportMailgunEvents extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'app:export-mailgun-events';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export Mailgun events and stores to a csv file.';

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


        $count = 100;
        $first = true;
        $domain = $this->argument('domain');
        $dest = $this->argument('dest');
        $filename = $this->getFilename($domain);
        $uri = $this->getUri($domain);

        while ($count == 100) {
            list($filename, $count, $uri) = $this->fetchAndExport($uri, $filename, $first);

            $first = false;
        }


        if (file_exists($dest)) {
            unlink($dest);
        }
        rename($filename, $dest);

        $this->info("file saved to " . $dest);
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            ['domain', \Symfony\Component\Console\Input\InputArgument::REQUIRED],
            ['apikey', \Symfony\Component\Console\Input\InputArgument::REQUIRED],
            ['dest', \Symfony\Component\Console\Input\InputArgument::REQUIRED],
        );
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


    protected $arraySeparator = '|';

    /**
     * [fetchAndExport description]
     *
     * @param [type] $uri          [description]
     * @param [type] $absolutePath [description]
     *
     * @return [type] [description]
     */
    public function fetchAndExport($uri, $absolutePath, $includeHeaders = false)
    {
        $domain = $this->argument('domain');
        $apiKey = $this->argument('apikey');

        $dir = pathinfo($absolutePath, PATHINFO_DIRNAME);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = fopen($absolutePath, "a");

        $fields = $this->getFields($domain);

        $this->info("Fetching <comment>$uri</comment>");
        $mailgun = new Mailgun($apiKey);
        $response = $mailgun->get($uri);

        $events = $response->http_response_body->items;
        // recursive object to array
        $events = json_decode(json_encode($events), true);

        if ($includeHeaders) {
            $headers = $fields;
            fputcsv($file, $headers);
        }


        foreach ($events as $event) {
            $row = [];
            $createdOn = Carbon::createFromTimestampUTC((int)$event['timestamp']);
            $event['utc-time'] = $createdOn->toDateTimeString();

            foreach ($fields as $f) {
                $row[] = array_get($event, $f);
            }
            $row = $this->serializeArrays($row);
            fputcsv($file, $row);
        }

        fclose($file);

        $uri = $response->http_response_body->paging->next;

        return array($absolutePath, count($events), $uri);
    }

    protected function getFields($domain)
    {
        $files = glob(base_path('resources/samples') . '/*.json');
        $custom = base_path("resources/custom/$domain.json") ;
        if (file_exists($custom)) {
            $files[] = $custom;
        }

        $record = [];
        $record['utc-time'] = null;

        foreach ($files as $f) {
            $data = json_decode(file_get_contents($f), true);
            $record = $record + static::array_dot($data);
        }

        $keys = array_keys($record);

        // remove duplicated prefixes
        // i.e.: we don't need user-variables if we have user-variables.foo

        foreach ($keys as $key => $value) {
            $found = array_filter($keys, function ($other) use ($value) {
                return $value != $other and strpos($other, $value) === 0;
            });
            if ($found) {
                unset($keys[$key]);
            }
        }

        return $keys;
    }


    protected function serializeArrays($record)
    {
        foreach ($record as $key => $value) {
            if (is_array($value)) {
                $deep = array_filter($value, function ($v) {
                    return is_array($v);
                });
                $associative = array_filter(array_keys($value), function ($v) {
                    return !is_numeric($v);
                });
                if ($deep || $associative) {
                    $record[$key] = json_encode($value, JSON_UNESCAPED_SLASHES);
                } else {
                    $record[$key] = join($this->arraySeparator, $value);
                }

            }
        }
        return $record;
    }

    /**
     * Versione modificata per preservare gli array vuoti
     *
     * @param $array
     * @param string $prepend
     * @return array
     */

    public static function array_dot($array, $prepend = '')
    {
        $results = array();

        foreach ($array as $key => $value) {
            if (is_array($value) && !$value) {
                $value = ''; // fake value
                $array[$key] = '';
            }
            if (is_array($value)) {
                $results = array_merge($results, static::array_dot($value, $prepend . $key . '.'));
            } else {
                $results[$prepend . $key] = $value;
            }
        }

        return $results;
    }

    protected function getFilename($domain)
    {
        $filename = $domain . '-' . date('Y-m-d_H-i-s') . '.csv';
        return storage_path('exports/' . $filename);
    }

    protected function getUri($domain)
    {
        return $domain . '/events';
    }
}

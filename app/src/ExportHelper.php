<?php

use Carbon\Carbon;
use Mailgun\Mailgun;

class ExportHelper
{
    /**
     * [fetchAndExport description]
     *
     * @param [type] $uri          [description]
     * @param [type] $absolutePath [description]
     *
     * @return [type] [description]
     */
    public function fetchAndExport($uri, $absolutePath)
    {
        $dir = pathinfo($absolutePath, PATHINFO_DIRNAME);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = fopen($absolutePath, "a");

        $apiKey = $_ENV['Mailgun.apiKey'];
        $domain = $_ENV['Mailgun.domain'];

        $fields = $this->getFields();

        $mailgun = new Mailgun($apiKey);
        $response = $mailgun->get($uri);
        $events = $response->http_response_body->items;
        // recursive object to array
        $events = json_decode(json_encode($events), true);


        $row = $fields;
        fputcsv($file, $row);

        foreach ($events as $event) {
            $row = [];
            $createdOn = Carbon::createFromTimestampUTC((int)$event['timestamp']);
            $event['utc-time'] = $createdOn->toDateTimeString();

            foreach ($fields as $f) {
                $row[] = array_get($event, $f);
            }
            $row = $this->replaceEmptyArrays($row);
            fputcsv($file, $row);
        }

        fclose($file);

        $parts = explode('/events/', $response->http_response_body->paging->next, 2);

        return array($response, count($events), "$domain/events/" . $parts[1]);
    }

    protected function getFields()
    {
        $files = glob(base_path('resources/samples') . '/*.json');
        $record = [];
        $record['utc-time'] = null;

        foreach ($files as $f) {
            $data = json_decode(file_get_contents($f), true);
            $record = $record + static::array_dot($data);
        }

        return array_keys($record);
    }


    protected function replaceEmptyArrays($record)
    {
        foreach ($record as $key => $value) {
            if (is_array($value)) {
                $record[$key] = '';
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
}

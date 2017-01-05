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

        $mailgun  = new Mailgun($apiKey);
        $response = $mailgun->get($uri);
        $events   = $response->http_response_body->items;
        // recursive object to array
        $events = json_decode(json_encode($events), true);

        $fields = $this->getFields();

        $row     = $fields;
        fputcsv($file, $row);

        foreach ($events as $event) {
            $row     = [];

            $createdOn = Carbon::createFromTimestampUTC((int)$event['timestamp']);
            $row[]     = $createdOn->toDateTimeString();

            foreach ($fields as $f) {
                $row[] = array_get($event, $f);
            }

            fputcsv($file, $row);
        }

        fclose($file);

        $parts = explode('/events/', $response->http_response_body->paging->next, 2);

        return array($response, count($events), "$domain/events/" . $parts[1]);
    }

    protected function getFields()
    {
        $files = glob(base_path('resources/samples').'/*.json');
        $record = [];
        foreach ($files as $f) {
            $data = json_decode(file_get_contents($f), true);
            $record = $record + array_dot($data);
        }
        return array_keys($record);
    }
}

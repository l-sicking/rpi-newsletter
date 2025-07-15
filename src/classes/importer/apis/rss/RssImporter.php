<?php

namespace RPI_ls_Newsletter\classes\importer\apis\rss;

use RPI_ls_Newsletter\classes\importer\Importer;
use RPI_ls_Newsletter\classes\importer\rss\DOMDocument;
use Rpi_ls_Newsletter\traits\Rpi_ls_Logging;



class RssImporter implements Importer
{
    use Rpi_ls_Logging;

    function __construct()
    {

    }

    public function fetch_posts($endpoint)
    {
        $rss = new DOMDocument();
        $rss->load('http://example.com/rss');

        foreach ($rss->getElementsByTagName('item') as $node) {
            $title = $node->getElementsByTagName('title')->item(0)->nodeValue;
            $link = $node->getElementsByTagName('link')->item(0)->nodeValue;
            $description = $node->getElementsByTagName('description')->item(0)->nodeValue;

            $post_data = array(
                'post_title' => wp_strip_all_tags($title),
                'post_content' => $description,
                'post_status' => 'publish',
                'post_type' => 'post',
            );

            wp_insert_post($post_data);
        }
    }

    public function fetch_post($instanz_id, $post_id)
    {
        // TODO: Implement fetch_post() method.
    }

    public function get_configuration_fields()
    {
        return [
            'rrs_url' => 'RRS Endpoint URL',
        ];
    }
}
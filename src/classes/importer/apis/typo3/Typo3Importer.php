<?php

namespace RPI_ls_Newsletter\classes\importer\apis\typo3;


use RPI_ls_Newsletter\classes\importer\Importer;

class Typo3Importer implements Importer
{
    function __construct()
    {

    }

    public function fetch_posts($endpoint)
    {
        // TODO: Implement fetch_posts() method.
    }

    public function fetch_post($instanz_id, $post_id)
    {
        // TODO: Implement fetch_post() method.
    }
    public function get_configuration_fields(){
        return [
            'typo3_url' => 'Typo3 Endpoint URL',
        ];
    }
}
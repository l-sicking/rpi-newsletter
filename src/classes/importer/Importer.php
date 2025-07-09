<?php
namespace RPINewsletter\classes\importer;



interface  Importer
{
    /**
     * Function should contain logic to fetch post information for respective
     * @param $instanz_id
     * @return mixed
     */
    public function fetch_posts($instanz_id);

    /**
     * Fetches a specific post based on the provided instance ID and post ID.
     *
     * @param mixed $instanz_id The ID of the instance from which the post should be retrieved.
     * @param mixed $post_id The ID of the specific post to fetch.
     */
    public function fetch_post($instanz_id, $post_id);

    /**
     * Retrieves the configuration fields required for the setup or operation.
     *
     * @return array An associative array containing the configuration fields.
     *  {
     *      $input_type the HTML input of the displayed type
     *      $field_key the identifier of the field
     *      $field_label the label of the field
     *  }
     */
    public function get_configuration_fields();


}
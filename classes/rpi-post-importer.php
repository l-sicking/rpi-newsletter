<?php

class RPIPostImporter
{
    private $logging;

    public function rpi_import_post($api_url = '', $status_ignorelist = [], $term_mapping = [], $dryrun = false, $logging = true, $graphql = false, $graphql_body = '')
    {
        $logging = true;


        $this->setLogging($logging);

        //TODO change on end of development

        $posts = [];

        if (boolval($graphql)) {

            $this->log('Start des Graphql Importvorgangs.');


            $data = json_encode(array('query' => $graphql_body));
            $response = wp_remote_post($api_url, array(
                'body' => $data,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
            ));

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();

                $this->log("Something went wrong: $error_message");
                return false;
            } else {
                // Handle the response
                $response_body = wp_remote_retrieve_body($response);
                $this->log("Response received");
            }

            $response = json_decode($response['body'], true);

            while (is_array($response)) {
                if (key_exists('posts', $response)) {
                    $response = reset($response);
                    break;
                } else {
                    $response = reset($response);
                }
            }


            foreach ($response as $item) {


                $post_data = [];
                if (!empty($item["post"]['content'])) {
                    $post_data['post_content'] = $item["post"]['content'];
                }
                if (!empty($item["post"]['excerpt'])) {
                    $post_data['post_excerpt'] = $item["post"]['excerpt'];
                }
                if (!empty($item["post"]['title'])) {
                    $post_data['post_title'] = $item["post"]['title'];
                }
                if (!empty($item['date'])) {
                    $post_data['post_date'] = $item['date'];
                }
                if (!empty($item['url']) && !empty($item['import_id'])) {
                    $post_data['meta_input'] = array(
                        'import_link' => $item['url'],
                        'import_id' => $item['import_id']);
                }
                if (!empty($item['categories'])) {
                    $post_data['categories'] = $item['categories'];
                }
                if (!empty($item['tags'])) {
                    $post_data['tags'] = $item['tags'];
                }
                if (!empty($item['image'])) {
                    $post_data['featured_media'] = $item['image'];
                }
                $post_data['post_author'] = 1;
                $post_data['post_status'] = 'publish';
                $post_data['post_type'] = 'post';

                if (isset($item['import_id'])) {
                    $this->log('import id found : ' . $item['import_id']);
                    $existing_post = get_posts([
                        'meta_query' => array(
                            array(
                                'key' => 'import_id',
                                'value' => $item['import_id'],
                                'compare' => '=',
                            )
                        )
                    ]);
                    $post_exists = $this->check_if_post_exists($existing_post);
                    if (!$dryrun) {
                        if ($post_exists) {
                            $post_data['ID'] = $post_exists;
                            $post_id = $this->create_post($post_data, true);
                        } else {
                            $post_id = $this->create_post($post_data);
                        }
                    }
                } else {

                    $this->log('Import ID not found aborting post import');
                }
//                if (!$dryrun) {
//                    $post_id = $this->create_post($post_data, true);
//                }
                // Execute Term Mapping or add default Term
                $this->term_mapping($post_id, $term_mapping, $post_data);
                if ($post_id && !is_wp_error($post_id)) {
                    $posts[] = $post_id;
                } else {
                    $this->log('An Error occured whilst creating a post.' . is_wp_error($post_id) ? $post_id->get_error_message() : 'No error Message');
                }
            }


        } else {
            $this->log('Start des Importvorgangs.');

            $url = sanitize_url($api_url);

            // HTTP request to the API
            $response = wp_remote_get($url);

            // Check if the request was successful
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();

                $this->log("Something went wrong: $error_message");
                return false;
            } else {
                // Handle the response
                $this->log("Response received");
                $posts = json_decode(wp_remote_retrieve_body($response), true);

            }

            $this->log(count($posts) . ' Posts received through remote');

            // Fetch all pages of results
//            $news_items = $this->fetch_all_pages($url, $posts);

            foreach ($posts as $item) {

                if (in_array($item['status'], $status_ignorelist)) {
                    continue;
                }

                // Erstellen eines neuen Beitrags für jede Sprache.

                $post_data = array(
                    'post_author' => 1, // oder einen dynamischen Autor
                    'post_content' => $item['content']['rendered'],
                    'post_date' => $item['date'],
                    'post_title' => $item['title']['rendered'],
                    'post_status' => 'publish',
                    'post_type' => 'post',
                    'meta_input' => array(
                        'import_link' => $item['link'],
                        'import_id' => $item['id'], // oder eine andere eindeutige ID
                    ),
                    'categories' => $item['categories'],
                    'tags' => $item['tags'],
                );

                $this->log('looking for media links');
                if (wp_http_validate_url($item['featured_media']) || filter_var($url, FILTER_VALIDATE_URL)) {
                    $post_data['featured_media'] = $item['featured_media'];
                    $this->log('media links found under featured_media');
                } elseif (!empty($item['featured_media']) && key_exists('_links', $item)) {
                    if (count($item['_links']['wp:featuredmedia']) > 1) {
                        foreach ($item['_links']['wp:featuredmedia'] as $featured_media) {
                            $post_data['featured_media'][] = $featured_media['href'];
                        }
                        $this->log('Found media under links');
                    } else {
                        $post_data['featured_media'][] = reset($item['_links']['wp:featuredmedia'])['href'];
                    }
                } else {
                    $this->log('Found no valid media aborting media import ... ' . var_export($item['featured_media'], true));
                }

                if (isset($item['id'])) {
                    $existing_post = get_posts([
                        'meta_query' => array(
                            array(
                                'key' => 'import_id',
                                'value' => $item['id'],
                                'compare' => '=',
                            )
                        )
                    ]);
                    $post_exists = $this->check_if_post_exists($existing_post);
                    if (!$dryrun) {
                        if ($post_exists) {
                            $post_data['ID'] = $post_exists;
                            $post_id = $this->create_post($post_data, true);
                        } else {
                            $post_id = $this->create_post($post_data);
                        }
                    }
                }

                // Execute Term Mapping or add default Term
                if (isset($post_id)) {
                    $this->term_mapping($post_id, $term_mapping, $item);

                    $posts[] = $post_id;
                }
            }
        }

        $this->log('Importvorgang abgeschlossen.');
        return $posts;


    }

    private function log($message)
    {
        if ($this->getLogging()) {

            $log_file = WP_CONTENT_DIR . '/rpi_post_importer_log.txt'; // Pfad zur Log-Datei
            $timestamp = current_time('mysql');
            $entry = "{$timestamp}: {$message}\n";

            file_put_contents($log_file, $entry, FILE_APPEND);
        }
    }

    /**
     * @return mixed
     */
    public function getLogging()
    {
        return $this->logging;
    }

    /**
     * @param mixed $logging
     */
    public function setLogging($logging)
    {
        $this->logging = $logging;
    }

    public function check_if_post_exists($existing_post)
    {
        $post_exists = false;
        if (count($existing_post) > 0) {
            $existing_post = reset($existing_post);
            $this->log("Imported Post $existing_post->ID already exists! Updating ...");
            $post_exists = $existing_post->ID;
        }
        return $post_exists;
    }

    private function create_post($item, $update = false)
    {

        if (!empty($item['post_title']) && !empty($item['post_content'])) {
            // Annahme: $item enthält alle notwendigen Informationen

            $this->log("Trying to create Post with provided Data");
            // Erstellen des Beitrags
            $post_id = wp_insert_post($item, true);
            if (is_wp_error($post_id)) {
                $this->log($post_id->get_error_message());
                return false;
            }
            // Überprüfen, ob der Beitrag erfolgreich erstellt wurde.
            if ($post_id && !is_wp_error($post_id)) {

                // Medieninhalte hinzufügen


                if (!empty($item['featured_media']) && !$update) {
                    $this->log('Trying to import media: ' . var_export($item['featured_media'], true));
                    if (is_array($item['featured_media']) && key_exists('url', $item['featured_media'])) {
                        $this->log('Importing via url in featured media');
                        $media_id = $this->import_media($item['featured_media']['url'], $post_id, false);

                    } else {
                        $media_id = $this->import_media($item['featured_media'], $post_id);

                    }

                    if ($media_id) {
                        $this->log('Media Imported');
                    } else {
                        $this->log('An Error has occurred while importing Media');
                    }

                }


                // Kategorien und Tags hinzufügen
                if (!empty($item['categories'])) {
                    $this->log('Trying to assign Categories Terms: ' . var_export($item['categories'], true));
                    $this->assign_terms($post_id, $item['categories'], 'category');
                    $this->log('Assignment of Categories Terms complete');
                }
                if (!empty($item['tags'])) {
                    $this->log('Trying to assign Tags Terms: ' . var_export($item['tags'], true));
                    $this->assign_terms($post_id, $item['tags'], 'post_tag');
                    $this->log('Assignment of Tags Terms complete');

                }

                $this->log("Beitrag (ID: $post_id) erstellt: '{$item['post_title']}'");
                return $post_id;
            }
        }
        return false;

    }

    private function import_media($media_url, $post_id = 0, $wp_json = true)
    {

        if ($wp_json) {
            $this->log('Trying to import Media via wp_json');
        } else {
            $this->log('Trying to import Media via direct Download');
        }
        if (!$media_url) {
            $this->log('No Media URL provided');
            return false;
        }

        if (is_array($media_url)) {

            if ($wp_json) {
                $this->log('Multiple media urls received handling them as via wp_json API request');
                $attachment_id = array();
                foreach ($media_url as $url) {
                    $attachment_id[] = $this->wp_insert_attachment_from_url($url, $post_id, $wp_json);

                }
                $this->log('Added ' . count($attachment_id) . ' Attachments to post');
                return $attachment_id;
            } else {
                $this->log('Multiple media urls received but no wp_json urls cant compute! returning ...');
                return false;
            }


        } else {
            // Der Dateiname wird aus der URL extrahiert.
            $this->log('Single Media File download via URL' . var_export($media_url, true));
            $file_name = basename($media_url);

            $this->log(var_export($file_name, true));
            // Temporärdatei erstellen.
            $temp_file = download_url($media_url);
            if (is_wp_error($temp_file)) {
                $this->log('Trying to download the media caused the following Error: ' . $temp_file->get_error_message());
                return false;
            }

            // Dateiinformationen vorbereiten.
            $file = array(
                'name' => $file_name,
                'type' => mime_content_type($temp_file),
                'tmp_name' => $temp_file,
                'error' => 1,
                'size' => filesize($temp_file),
            );
            $this->log(var_export($file, true));


            if (!function_exists('media_handle_sideload')) {
                $this->log('function media_handle_sideload not found, using wp_insert_attachment instead');


                $attachment_id = $this->wp_insert_attachment_from_url($media_url, $post_id, $wp_json);

                if (!$attachment_id) {
                    $this->log("Attachment of '$media_url' to Post '$post_id' failed");
                    return false;

                } else {
                    set_post_thumbnail($post_id, $attachment_id);
                    $this->log("Added Thumbnail of '$media_url' to '$post_id', with '$attachment_id'");
                }

            } else {
                // Die Datei wird der Medienbibliothek hinzugefügt.
                $media_id = media_handle_sideload($file, $post_id);
            }


            if (is_wp_error($media_id)) {
                $this->log('Media import Failed' . $media_id->get_error_message());
                @unlink($temp_file);
                return false;
            }

            return $media_id;
        }

    }

    function wp_insert_attachment_from_url($url, $parent_post_id = null, $wp_json = true)
    {
        if ($wp_json) {

            $url = sanitize_url($url);

            // HTTP request to the API
            $response = wp_remote_get($url);

            $attachments = json_decode(wp_remote_retrieve_body($response), true);
            foreach ($attachments as $attachment) {

                if (empty($response['guid']['rendered'])) {
                    $this->log("RESPONSE ERROR: Array Path ['guid']['rendered'] not found in " . var_export($response));
                    return false;
                }
                $upload = wp_upload_bits(basename($url), null, $response['guid']['rendered']);
                if (!empty($upload['error'])) {
                    return false;
                }

                $file_path = $upload['file'];
                $file_name = basename($file_path);
                $file_type = wp_check_filetype($file_name, null);
                $attachment_title = sanitize_file_name(pathinfo($file_name, PATHINFO_FILENAME));
                $wp_upload_dir = wp_upload_dir();

                $post_info = array(
                    'guid' => $wp_upload_dir['url'] . '/' . $file_name,
                    'post_mime_type' => $file_type['type'],
                    'post_title' => sanitize_text_field($response['title']['rendered']),
                    'post_excerpt' => sanitize_text_field($response['caption']['rendered']),
                    'post_content' => sanitize_text_field($response['description']['rendered']),
                    'post_status' => 'inherit',
                );

                // Create the attachment.
                $attach_id = wp_insert_attachment($post_info, $file_path, $parent_post_id);

                // Include image.php.
                require_once ABSPATH . 'wp-admin/includes/image.php';

                // Generate the attachment metadata.
                $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);

                // Assign metadata to attachment.
                wp_update_attachment_metadata($attach_id, $attach_data);

                return $attach_id;
            }

        } else {

//            if (!empty())
//            TODO ADD check if description and caption of thumbnail are present if not abort
            if (!class_exists('WP_Http')) {
                require_once ABSPATH . WPINC . '/class-http.php';
            }


            $http = new WP_Http();
            $response = $http->request($url);
            if (200 !== $response['response']['code']) {
                return false;
            }

            $upload = wp_upload_bits(basename($url), null, $response['body']);
            if (!empty($upload['error'])) {
                return false;
            }

            $file_path = $upload['file'];
            $file_name = basename($file_path);
            $file_type = wp_check_filetype($file_name, null);
            $attachment_title = sanitize_file_name(pathinfo($file_name, PATHINFO_FILENAME));
            $wp_upload_dir = wp_upload_dir();

            $post_info = array(
                'guid' => $wp_upload_dir['url'] . '/' . $file_name,
                'post_mime_type' => $file_type['type'],
                'post_title' => sanitize_text_field($attachment_title),
                'post_status' => 'inherit',
            );

            // Create the attachment.
            $attach_id = wp_insert_attachment($post_info, $file_path, $parent_post_id);

            // Include image.php.
            require_once ABSPATH . 'wp-admin/includes/image.php';

            // Generate the attachment metadata.
            $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);

            // Assign metadata to attachment.
            wp_update_attachment_metadata($attach_id, $attach_data);

            return $attach_id;
        }


    }

    private function assign_terms($post_id, array $term_ids, $taxonomy)
    {
        if (taxonomy_exists($taxonomy)) {
            $this->log('Sanitizing imported post terms');
//        $term_ids = $this->sanitize_imported_post_terms($term_ids);

            if (is_array(reset($term_ids))) {
                $term_ids = array_column(reset($term_ids), 'name');
            }
            $this->log("Assigning terms with IDs " . var_export($term_ids, true));
            foreach ($term_ids as $term_id) {

                if (is_numeric($term_id)) {
                    $this->log('provided Array contains Id Import URL for Terms required');
                } else {
                    $existing_term = term_exists($term_id, $taxonomy);
                    if (!$existing_term) {
                        $this->log('Provided Term:' . $term_id . ' doesn\'t exist. creating term');
                        wp_insert_term($term_id, $taxonomy);
                    }
                    $new_term = get_term_by('name', $term_id, $taxonomy);

                    $this->log('Passed Term Array ' . var_export($new_term, true));
                    wp_set_post_terms($post_id, array($new_term->term_id), $taxonomy, true);;

                }
            }

        } else {
            $this->log('Error Taxonomy' . $taxonomy . ' does not exist');
        }


//        $term_ids = $this->sanitize_imported_post_terms($term_ids);
//        foreach ($term_ids as $term_id) {
//            // Namen des Terms aus der Quelle holen
//            if (is_numeric($term_id)) {
//                $term_name = get_term_by('id', $term_id, $taxonomy)->name;
//            }
//            // Term-ID im Zielblog übersetzen
//            $translated_term_id = $this->check_term_if_exists_and_create($term_name, $taxonomy);
//            if (!empty($translated_term_id)) {
//                wp_set_object_terms($post_id, $translated_term_id, $taxonomy, true);
//            }
//        }
    }

    private function term_mapping($post_id, $term_mapping, $item)
    {
        if (is_array($term_mapping)) {
            foreach ($term_mapping as $term) {
                if (!empty($term['default_term']) && !empty($term['target_tax'])) {
                    if (!empty($term['source_tax']) && array_key_exists($term['source_tax'], $item)) {
                        wp_set_post_terms($post_id, $item[$term['source_tax']], $term['target_tax']);
                    } else {
                        wp_set_post_terms($post_id, $term['default_term'], $term['source_tax']);
                    }
                }

            }
        }
    }

    public function fetch_all_pages($api_url, $data, $page = 1)
    {
        $per_page = 10; // Number of items per page
        $args = array(
            'per_page' => $per_page,
            'page' => $page,
        );

        $request_url = add_query_arg($args, $api_url);
        $response = wp_remote_get($request_url);

        if (is_wp_error($response)) {
            return $data; // Return existing data if request fails
        }

        $next = json_decode(wp_remote_retrieve_body($response), true);
        $data = array_merge($data, $next);

        // Check if there are more pages to fetch
        if (count($next) == $per_page) {
            $page++;
            $data = $this->fetch_all_pages($api_url, $data, $page); // Recursively fetch next page
        }

        return $data;
    }

    private function sanitize_imported_post_terms($term_ids)
    {
        foreach ($term_ids as $key => $term_id) {
            if (is_array($term_id)) {
                array_walk_recursive($term_ids, function ($item, $key) use (&$results) {
                    $results[$key] = $item;
                });
                $term_ids = array_merge($term_ids, $results);
            } else {
                $term_ids[$key] = $term_id;
            }
        }
        return $term_ids;
    }

    private function check_term_if_exists_and_create($source_term_name, $taxonomy)
    {
        // Überprüfen, ob ein Term mit diesem Namen im Zielblog existiert.
        $term = get_term_by('name', $source_term_name, $taxonomy);

        // Wenn der Term existiert, gibt die ID zurück.
        if (is_a($term, 'WP_Term')) {
            return $term->term_id;
        }

        // Wenn der Term nicht existiert, erstelle einen neuen Term.
        $new_term = wp_insert_term($source_term_name, $taxonomy);

        // Überprüfen, ob die Erstellung erfolgreich war.
        if (is_wp_error($new_term)) {
            // Fehlerbehandlung, eventuell Logging.
            $this->log("Failed to translate term '$source_term_name' to '$taxonomy' " . $new_term->get_error_message());
            return false;
        }

        // Gibt die ID des neu erstellten Terms zurück.
        return $new_term['term_id'];
    }


}

<?php

namespace RPI_ls_Newsletter\classes\importer;
require_once  dirname(__DIR__, 3).'/vendor/autoload.php';
use RPI_ls_Newsletter\classes\importer\Importer;
use Rpi_ls_Newsletter\traits\Rpi_ls_Logging;
/**
 * Class ImporterSetup
 * Handles the setup and functionality for the Instanz Importer.
 */
class ImporterSetup
{
    use Rpi_ls_Logging;
    /**
     * Constructor method to set up hooks for WordPress actions.
     */
    function __construct()
    {
        $this->debugmode = true;
        // Adds meta boxes to the Instanz post type.
        add_action('add_meta_boxes', array($this, 'instanz_add_meta_boxes'));
        // Saves meta box data when a post is saved.
        add_action('save_post', array($this, 'instanz_save_meta_box'));
        // Schedules and runs imports via a custom cron hook.
        add_action('wp_post_importer_cron_hook', array($this, 'run_instanz_imports'));

    }

    /**
     * Adds custom meta boxes for the Instanz post type.
     */
    function instanz_add_meta_boxes()
    {
        add_meta_box(
            'instanz_importer_settings', // Unique ID for the meta box.
            'Importer Settings',         // Title of the meta box.
            [$this, 'instanz_render_meta_box'],   // Callback function to render the meta box.
            'instanz',                   // Post type where the box appears.
            'normal',                    // Context (normal, side, etc.).
            'high'                       // Priority.
        );
    }

    /**
     * Renders the meta box UI for the Instanz post type.
     * @param \WP_Post $post The current post object.
     * @throws \ReflectionException
     */
    function instanz_render_meta_box($post)
    {


        // Retrieve saved metadata.
        $selected_importer = get_post_meta($post->ID, 'importer_type', true);
        $importer_config = get_post_meta($post->ID, 'importer_config', true);
        // Fetch available importers.

        $importers = $this->get_available_apis();


        // Output the meta box form.
        ?>
        <p>
            <label for="importer_type">Select Importer:</label>
            <select name="importer_type" id="importer_type">
                <?php foreach ($importers as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($selected_importer, $key); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <div id="importer-fields">
            <?php
            // Dynamically render importer-specific fields if an importer is selected.
            if ($selected_importer) {
                echo $this->instanz_render_importer_fields($selected_importer, $importer_config);
            }
            ?>
        </div>
        <?php
    }

    /**
     * Scans the specified directory for API sub-directories, infers class names,
     * and checks for interface implementation using PSR-4 principles.
     *
     *
     * @return array An associative array where keys are API identifiers (lowercase)
     * and values are display names (from get_display_name() method).
     */
    function get_available_apis(  ) {
        $apis = array();


        $dir = plugin_dir_path( __FILE__ ) . 'apis/';
        // Define the fully qualified class name (FQCN) for the interface.
        // This assumes your interface is correctly autoloaded.
        $interface_fqcn ='RPI_ls_Newsletter\\classes\\importer\\Importer';

        // It's good practice to ensure the interface is available.
        // With PSR-4, just referencing it should trigger autoloading if needed.
        // However, if it's not yet loaded for some reason, class_exists() will return false.
        if ( ! interface_exists( $interface_fqcn ) ) {
            $this->log_message( 'RPI_ls_Newsletter Error: APIImporterInterface is not loaded or does not exist.' );
            // You might consider manually requiring it here ONLY if you're sure
            // your autoloader isn't set up correctly for this specific file,
            // but ideally, the autoloader should handle it.
            // E.g., if it's not in the main autoloader, you might do:
            // require_once plugin_dir_path( dirname( __FILE__, 2 ) ) . 'src/classes/importer/APIImporterInterface.php';
            return $apis;
        }
        if ( is_dir( $dir ) ) {
            $items = scandir( $dir );
            foreach ( $items as $item ) {
                if ( $item === '.' || $item === '..' ) {
                    continue;
                }


                $api_directory = $dir . $item . DIRECTORY_SEPARATOR; // Path to e.g., .../apis/graphql/
                $class_file_name = ucfirst( $item ) . 'Importer.php'; // e.g., GraphqlImporter.php
                $class_file_path = $api_directory . $class_file_name; // Full path to the expected class file

                // Check if it's a directory and the expected class file exists within it.
                // This is a sanity check, but the autoloader's job is to make the class available
                // when referenced by its FQCN.
                if ( is_dir( $api_directory ) && file_exists( $class_file_path ) ) {


                    // Construct the Fully Qualified Class Name (FQCN) based on your namespace structure.
                    // Assuming RPI_ls_Newsletter\Importer\APIs\<ApiName>\<ApiName>Importer
                    $namespace_segment = ucfirst( sanitize_title( $item ) ); // e.g., 'Graphql', 'Wpjson'
                    $class_fqcn = 'RPI_ls_Newsletter'. strtolower('\\classes\\Importer\\APIs\\' . $namespace_segment . '\\' ). ucfirst( $item ) . 'Importer';

                    // With PSR-4, referencing the FQCN here will trigger the autoloader
                    // if the class hasn't been loaded yet.

                    if ( class_exists( $class_fqcn ) ) {
                        // Now, check if the loaded class implements the required interface.
                        // This relies on your autoloader correctly loading the class.
                        if ( in_array( $interface_fqcn, class_implements( $class_fqcn ) ) ) {
                            $api_key = sanitize_title( $item ); // e.g., 'graphql', 'wpjson'

//                            // Instantiate the class to get its display name.
//                            // This is safe because we've confirmed it implements the interface.
//                            $this->log_message( ucfirst( $item ) . 'Importer' );
//                            $class_name = ucfirst( $item ) . 'Importer';
//                            $temp_instance = new $class_name();
//                            $this->log_message($temp_instance);
//
////                            $api_display_name = $temp_instance->get_display_name();

                            $apis[ $class_fqcn] =$api_key;
                        } else {
                            // Class exists, but doesn't implement the required interface.
                            $this->log_message( sprintf(
                                'RPI_ls_Newsletter Debug: Class "%s" found but does not implement interface "%s".',
                                $class_fqcn,
                                $interface_fqcn
                            ) );
                        }
                    } else {
                        // Class file exists, but class_exists() returned false.
                        // This often indicates an issue with the class definition itself (e.g., syntax error)
                         // or a problem with the autoloader's mapping for this specific FQCN.
//                        $this->log_message( sprintf(
//                            'RPI_ls_Newsletter Debug: Expected class "%s" in file "%s" not found or failed to autoload.',
//                            $class_fqcn,
//                            $class_file_path
//                        ) );
                    }
                }
            }
        }
        return $apis;
    }


//    function get_classes_implementing_interface(): array
//    {
//        $directory = plugin_dir_path(__FILE__) . 'apis/';
//        $interface = 'RPI_ls_Newsletter\classes\importer\Importer';
//        $classes = [];
//        $directoryiterator = new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS);
//        $iterator = new \RecursiveIteratorIterator($directoryiterator, \recursiveIteratorIterator::CHILD_FIRST);
//        foreach ($iterator as $file) {
//
//
//            if (is_a($file, 'SplFileInfo') && $file->isFile() && $file->getExtension() === 'php') {
//
//               $baseDir =  plugin_dir_path(__FILE__);
//                $relativePath = str_replace([$baseDir, '/', '.php'], ['', '\\', ''], $file->getPathname());
//                $class =  __NAMESPACE__.'\\'.$relativePath;
////                $class =  "RPI_ls_Newsletter\classes\importer\\".$relativePath;
//                var_dump($class);
//                if (class_exists($class)) {
//                    $reflectionClass = new \ReflectionClass($class);
//
//                    if ($reflectionClass->isInstantiable() && $reflectionClass->implementsInterface($interface)) {
//                        try {
//                            $implementations[] = $reflectionClass->newInstance();
//
//                        }
//                        catch (\Exception $e) {
//                            echo $e->getMessage();
//                        }
//                    }
//                }
//
////                var_dump(new $class() );
//
////
////                var_dump($file->getPathname());
////                var_dump(class_exists($file->getPathname()));
//
//                // Get defined classes AFTER including
//                foreach (get_declared_classes() as $class) {
//                    if (in_array($interface, class_implements($class))) {
//                        $classes[$class] = $class;
//                    }
//                }
//            }
//        }
//
//
//
//        return array_unique($classes);
//    }

    public static function getImplementations(string $directory, string $interface): array
    {
        $implementations = [];
        $files = glob($directory . '/*.php');

        foreach ($files as $file) {
            // Get class name from file path (requires careful parsing for robustness)
            $className = pathinfo($file, PATHINFO_FILENAME);

            // Construct full qualified class name based on your namespace
            // This assumes a 1:1 mapping of file name to class name in the DynamicClasses directory
            $fullQualifiedClassName = "YourPluginNamespace\\DynamicClasses\\{$className}";

            if (class_exists($fullQualifiedClassName)) {
                $reflectionClass = new \ReflectionClass($fullQualifiedClassName);

                // Check if it's instantiable and implements the interface
                if ($reflectionClass->isInstantiable() && $reflectionClass->implementsInterface($interface)) {
                    $implementations[] = $reflectionClass->newInstance();
                }
            }
        }

        return $implementations;
    }

    /**
     * Dynamically renders configuration fields for a selected importer class.
     * @param string $importer_class Name of the importer class.
     * @param array $saved_config Saved configuration values.
     * @return string HTML for the configuration fields.
     */
    function instanz_render_importer_fields($importer_class, $saved_config = [])
    {
        if (!class_exists($importer_class)) {
            return '<p>Invalid importer selected.</p>';
        }

        $importer = new $importer_class();
        if (!method_exists($importer, 'get_configuration_fields')) {
            return '<p>The selected importer does not define configuration fields.</p>';
        }

        // Get configuration fields from the importer class.
        $fields = $importer->get_configuration_fields();
        $html = '';
        foreach ($fields as $field_key => $label) {
            $value = $saved_config[$field_key] ?? '';
            $html .= '<p>';
            $html .= '<label for="' . esc_attr($field_key) . '">' . esc_html($label) . '</label>';
            $html .= '<input type="text" name="importer_config[' . esc_attr($field_key) . ']" value="' . esc_attr($value) . '" class="regular-text">';
            $html .= '</p>';
        }
        return $html;
    }

    /**
     * Runs the import process for all instances of the Instanz post type.
     */
    function run_instanz_imports()
    {
        // Fetch all Instanz posts.
        $instances = get_posts(['post_type' => 'instanz', 'numberposts' => -1]);

        foreach ($instances as $instance) {
            $importer_class = get_post_meta($instance->ID, 'importer_type', true);
            $config = get_post_meta($instance->ID, 'importer_config', true);

            if (!class_exists($importer_class)) {
                error_log("Instanz Import Error: Importer class $importer_class does not exist.");
                continue;
            }

            try {
                $importer = new $importer_class();
                $endpoint = $config['endpoint'] ?? ''; // Adjust according to importer requirements.
                $posts = $importer->fetch_posts($endpoint);
//                ImporterController::import_posts($posts); // Example: Call to import the fetched posts.
            } catch (\Exception $e) {
                error_log("Instanz Import Error: " . $e->getMessage());
            }
        }
    }

    /**
     * Saves the meta box data when a post is saved.
     * @param int $post_id The ID of the post being saved.
     */
    function instanz_save_meta_box($post_id)
    {
        // Save the selected importer type.
        if (array_key_exists('importer_type', $_POST)) {
            update_post_meta($post_id, 'importer_type', sanitize_text_field($_POST['importer_type']));
        }

        // Save the importer configuration data.
        if (array_key_exists('importer_config', $_POST)) {
            update_post_meta($post_id, 'importer_config', $_POST['importer_config']);
        }
    }
}

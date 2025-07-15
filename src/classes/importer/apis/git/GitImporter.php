<?php
namespace RPI_ls_Newsletter\classes\importer\apis\git;


use RPI_ls_Newsletter\classes\importer\git\Exception;
use RPI_ls_Newsletter\classes\importer\git\Parsedown;
use RPI_ls_Newsletter\classes\importer\git\RecursiveDirectoryIterator;
use RPI_ls_Newsletter\classes\importer\git\RecursiveIteratorIterator;
use RPI_ls_Newsletter\classes\importer\Importer;
use Symfony\Component\Yaml\Yaml;

/**
 * @see Importer
 *
 */
class GitImporter implements Importer
{
    private $repoUrl;
    private $localPath;
    private $parsedown;

    public function __construct($repoUrl = '', $localPath = null)
    {
//        if (!class_exists('Parsedown')) {
//            throw new Exception('Parsedown is not installed.');
//        }
//
//        if (!class_exists('Symfony\Component\Yaml\Yaml')) {
//            throw new Exception('Symfony YAML component is not installed.');
//        }
//
//        $this->repoUrl = $repoUrl;
//        $this->localPath = $localPath ?? sys_get_temp_dir() . '/git_importer_cache_' . md5($repoUrl);
//        $this->parsedown = new Parsedown();
    }

    public function fetch_posts($instanz_id)
    {
        // TODO: Implement fetch_posts() method.
    }

    public function fetch_post($instanz_id, $post_id)
    {
        // TODO: Implement fetch_post() method.
    }

    public function get_configuration_fields(){
        return [
            'repo_url' => 'Git Repository URL',
            'local_path' => 'Local Path',
        ];
    }
    public function getAllPostsWithCache(): array
    {
        $this->syncRepo();

        $imported = file_exists($this->getCacheFile())
            ? json_decode(file_get_contents($this->getCacheFile()), true)
            : [];

        $files = $this->findPostFiles();
        $newImported = [];
        $postArgsList = [];

        foreach ($files as $file) {
            $hash = md5_file($file);
            if (!isset($imported[$file]) || $imported[$file] !== $hash) {
                $parsed = $this->parseMarkdownFile($file);
                if ($parsed) {
                    $postArgsList[] = $this->getPostArgsFromData($parsed);
                    $newImported[$file] = $hash;
                }
            } else {
                $newImported[$file] = $hash;
            }
        }

        file_put_contents($this->getCacheFile(), json_encode($newImported));
        return $postArgsList;
    }

    private function syncRepo()
    {
        if (!file_exists($this->localPath)) {
            exec("git clone {$this->repoUrl} {$this->localPath}");
        } else {
            exec("cd {$this->localPath} && git pull");
        }
    }

    private function getCacheFile()
    {
        return $this->localPath . '/imported.json';
    }

    private function findPostFiles(): array
    {
        $postPath = $this->localPath . '/Website/content/posts';
        if (!is_dir($postPath)) return [];

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($postPath)
        );

        foreach ($iterator as $file) {
            if ($file->getFilename() === 'index.md') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function parseMarkdownFile($filepath): ?array
    {
        $content = file_get_contents($filepath);
        if (!preg_match('/^---\s*(.*?)\s*---\s*(.*)$/s', $content, $matches)) {
            return null;
        }

        $yaml = Yaml::parse($matches[1]);
        $body = $this->parsedown->text($matches[2]);

        return [
            'meta' => $yaml,
            'content' => $body,
        ];
    }

    public function getPostArgsFromData($data): ?array
    {
        $meta = $data['meta'];
        $content = $data['content'];

        $title = $meta['title'] ?? ($meta['commonMetadata']['name'] ?? 'Untitled');
        $excerpt = $meta['summary'] ?? ($meta['description'] ?? '');
        $slug = $meta['url'] ?? sanitize_title($title);

        $args = [
            'post_title' => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_name' => $slug,
            'post_status' => 'publish',
            'post_type' => 'post',
        ];

        $args['featured_media'] = $meta['cover']['image'];
        $args['tags_input'] = $meta['tags'];

        return $args;
    }

    public function getLatestPosts($limit = 5): array
    {
        $this->syncRepo();
        $files = $this->findPostFiles();

        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
        $files = array_slice($files, 0, $limit);

        $postArgsList = [];
        foreach ($files as $file) {
            $parsed = $this->parseMarkdownFile($file);
            if ($parsed) {
                $postArgsList[] = $this->getPostArgsFromData($parsed);
            }
        }

        return $postArgsList;
    }
}


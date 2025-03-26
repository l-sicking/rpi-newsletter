# Rpi Newsletter Plugin

![WordPress Plugin](https://img.shields.io/badge/WordPress-Plugin-brightgreen.svg)

## Description
The **Rpi Newsletter Plugin** is designed for the RPI Newsletter Mainserver. It provides functionality for importing and managing newsletter posts within a WordPress site.

## Table of Contents
- [Installation](#installation)
- [Usage](#usage)
- [Configuration](#configuration)
- [Contributing](#contributing)
- [License](#license)

## Installation

1. Download the plugin from the [GitHub repository](https://github.com/rpi-virtuell/rpi-newsletter).
2. Upload the plugin files to the `/wp-content/plugins/rpi-newsletter` directory, or install the plugin through the WordPress plugins screen directly.
3. Activate the plugin through the 'Plugins' screen in WordPress.

## Usage

Once activated, the plugin will:
- Add custom columns to the newsletter post types.
- Automatically import posts from specified API URLs during a cron job.
- Redirect posts to their original source if a specific meta field is present.

## Configuration

### Setting up API URLs
To import posts from external sources, you need to configure the API URLs in the plugin settings. Each instance of the newsletter should have its own set of API URLs.

### Adding Custom Columns
The plugin adds custom columns to the post types `instanz` and `newsletter-post`. These columns display specific terms associated with each post.

### Redirecting Posts
Posts can be redirected to their original source by setting the `import_link` meta field.

## Contributing

We welcome contributions to the Rpi Newsletter Plugin! To contribute:

1. Fork the repository on GitHub.
2. Create a new branch from `main` for your feature or bug fix.
3. Commit your changes with clear descriptions.
4. Create a pull request to merge your changes back into the `main` branch.

## License

This plugin is licensed under the GPL-2.0-or-later license. For more information, see the `LICENSE` file.

---

For more details and advanced configurations, refer to the [plugin documentation](https://github.com/rpi-virtuell/rpi-newsletter).

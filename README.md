# PureWiki - A Minimalist File-Based Wiki System

PureWiki is a lightweight, high-performance, and file-based wiki system built with native web technologies (PHP, HTML, CSS, JS). It is designed to be simple to deploy and manage, requiring absolutely no database, Node.js, npm, or complex build tools.

![GitHub Release](https://img.shields.io/github/v/release/PureWiki/PureWiki?label=Release&color=blue)
![GitHub latest](https://img.shields.io/github/v/tag/PureWiki/PureWiki?label=Latest)
![PHP Version](https://img.shields.io/badge/PHP%20%E2%89%A5%208.1-grey)
![GitHub code size in bytes](https://img.shields.io/github/languages/code-size/PureWiki/PureWiki?label=Code%20Size&color=2ea043)

> [!TIP]
> Try PureWiki without installation: [demo.purewiki.org](https://demo.purewiki.org).

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [File Structure](#file-structure)
- [License](#license)
- [Author](#author)
- [Contributions](#contributions)

## Basic Features

> [!IMPORTANT]
> PureWiki is currently in a very early stage of development and is actively being worked on. As a result, there may be unexpected bugs, incomplete features, and breaking changes in future updates. **Use in production environments with caution!**

- **Flat File Wiki**: All content is stored as JSON files, No Database Required.
- **No Node.js/Build Tools**: Built purely with native web technologies.
- **Modern Editor**: Integrated with Editor.js for clean, block-based editing, including custom plugins for Markdown, Grid layouts, Accordions, and more.
- **Built-in Caching**: Features a static file caching system for fast wiki page access.
- **Authentication & Roles**: Simple but effective role-based access control.
- **Drafts & History**: Automatically saves drafts and maintains a full version history of your pages.
- **Built-in Search**: Fast and simple full-text search capability.
- **Themes**: Ready-to-use theme, easy to create your own themes.
- **Integrated Updates**: Backup and update your wiki directly from the dashboard via GitHub releases.

## Installation

PureWiki is designed to be easy to install and run on any standard PHP web-hosting environment.

### Requirements

- PHP 8.1 or higher
- PHP Extensions: `gd` (for image processing), `zip` (for updates/backups), `curl` (for updates/fetching), `openssl`
- Web Server: 
  - **Apache** (with `mod_rewrite` enabled, `.htaccess` is included) 
  - **Nginx** (see [nginx.conf.example](nginx.conf.example) for required configuration)

### Setup Steps

1. **Download**: Download the latest release `.zip` from GitHub or clone the repository into your web server's document root.
2. **Permissions**: Ensure the web server has write access to the following directories (if they don't exist, ensure the parent directory is writable so the setup can create them):
   - `/pages`
   - `/config`
   - `/cache`
   - `/backups`
3. **Run Setup**: Navigate to your domain in a web browser (e.g., `https://yourdomain.com`). You will be automatically redirected to the setup page.
4. **Follow the Setup**: The setup page will guide you through creating your first admin account and setting your wiki's basic configuration.
5. **Login**: Once complete, you will be redirected to the admin dashboard, always reachable at `https://yourdomain.com/dashboard`.

## Configuration

Global settings can be managed through the Admin Dashboard (`/dashboard/settings`). This includes:

- Wiki Name & Logo
- Cache settings
- Custom CSS/JS injection
- SEO settings
- User management
- System status and manual updates

Configuration files are stored securely in the `/config` directory as JSON files.

## Usage

### The Frontend
The public face of your wiki is accessible at the root of your domain. It uses the `default` theme (based on PicoCSS) and renders your pages dynamically or serves them from the cache for maximum performance.

### The Dashboard
Access the administration area at `yourdomain.com/dashboard`. Here, authorized users can:
- **Manage Pages**: Create, edit, delete, and reorganize pages using a drag-and-drop tree view.
- **Edit Content**: Use the block-based editor to write content, embed media, or write raw HTML and Markdown.
- **Manage Media**: Upload and organize files and images.
- **Configure Settings**: Admins can change global wiki settings.

## File Structure

- `/pages/` - Where all your wiki content and media files are stored.
- `/config/` - System configuration and user data.
- `/cache/` - Frontend cache directory (includes search index).
- `/purewiki/` - The core application logic (PHP, Admin Dashboard, Editor JS, API).
- `/themes/` - Frontend layouts and styling.
- `/backups/` - System backups.

## License

This project is licensed under the GNU Affero General Public License v3.0 (AGPLv3). See the [LICENSE](LICENSE) file for details.

## Author / Maintainer

- [Oliver Weinhold](https://oliverweinhold.de/)

## Contributions

Contributions, issues, and feature requests are welcome! Feel free to use GitHub Issues and Discussions.

## Special Thanks

- [Editor.js](https://editorjs.io/) - For the block-based editor.
- [Pico.css](https://picocss.com/) - For the default theme styling.
- [Parsedown](https://github.com/erusev/parsedown) - For Markdown parsing.
- [PHPMailer](https://github.com/PHPMailer/PHPMailer) - For email functionality.
- [Prism.js](https://prismjs.com/) - For code highlighting.
- [Iconify](https://iconify.design/) - For icon management.
- [Croppie](https://github.com/foliotek/croppie) - For image cropping.

Also please check the [CREDITS.md](CREDITS.md) file for more information.
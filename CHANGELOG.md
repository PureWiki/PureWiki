# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Added a "View Page Source" button in the Page Settings title bar to view the raw JSON data of the page.

### Fixed
- Fixed array to string conversion error in the visual diff viewer when using nested lists.

## [0.2.1] - 2026-06-21

### Added
- Added user roles and permissions help text to the settings page.
- Added a visual diff-viewer that allows comparing historical page revisions with the current live version.

### Changed
- Changed page description JSON key to lowercase `description` (with backwards compatibility fallback for uppercase `Description`).

### Fixed
- Added translated titles for draft and private page indicators in the treeview.

## [0.2.0] - 2026-05-29

### Fixed

- Added `validateSessionUser()` to prevent that deleted or modified users could still access the backend until their session cookie expired.
- Changed Fallback for `pw_role` from `admin` to `reader` to prevent that users without a valid role get admin access.
- Added missing translation for `lang_available`.
- Fixed raw CSS styles and JavaScript code from `raw` blocks being indexed in search previews by filtering out style/script tags before indexing.
- Wrapped the SMTP email settings in a form tag and added `autocomplete="username"` and `autocomplete="current-password"` to prevent browser warnings.

### Changed
- Improved overall responsive design of the admin dashboard, sidebar and editor for smaller screens and mobile devices.
- Improved visibility and design of the Editor.js action buttons on mobile.
- Changed background color of toast notifications to a more visible background color in light mode.

## [0.2.0-alpha.12] - 2026-05-25

### Added
- Added `addInlineScript` and `getInlineScripts` to `AssetManager` to inject JS snippets into the footer
- Added Math block in the editor with preview to render math formulas using KaTeX

### Changed
- Changed `AssetManager::addScript` to accept an optional `$attr` parameter to control script attributes (like `defer` or `async`)
- Updated PHPMailer from 7.1.0 to 7.1.1

### Fixed
- Remove non-functional global settings toggles for inline tools (like `inlineCode`, `underline`) and make them always enabled
- Remove lang prefix from link-autocomplete hrefs to prevent double prefixes
- Implement Anti-CSRF tokens for authenticated requests to prevent CSRF attacks

## [0.2.0-alpha.11] - 2026-05-18

### Added
- Page Settings now support multilingual pages. A language switcher in the header allows switching between different languages

### Fixed
- Fixed virtual pages (e.g. footer/sidebars) always loading the default language version instead of the active language
- Fixed `base_url` (logo link) always pointing to the global root instead of the active language start page

### Changed
- Updated PHPMailer from 7.0.2 to 7.1.0

## [0.2.0-alpha.10] - 2026-05-16

### Fixed
- Fixed language-aware navigation: treeview, prev/next, breadcrumbs, page includes, and snippets now correctly respect the active language

### Added
- Added `router.php` to support the PHP built-in development server with correct URL rewriting and asset handling

## [0.2.0-alpha.9] - 2026-05-15

### Fixed
- Fixed an issue where text between angle brackets `< >` was incorrectly stripped from the rendered output by introducing a custom `sanitizeInlineHtml` function
- Fixed a problem in the used Editor.js Inline Code Tool where applying inline code would fail if another code tag already existed in the same block

### Added
- Added custom `editor-inline-code.js` to fix the selection problem
- Added dropdown style to the language switcher (`{{ macro:lang_switcher | dropdown }}`)

## [0.2.0-alpha.8] - 2026-05-11

### Added
- Added multilingual (i18n) support for pages, routing, and search indexing
- Added hreflang tags to XML sitemap for multilingual support
- Added language switcher macro and integrate it into the default theme

### Changed
- Improved error handling and path security in core filesystem operations

## [0.2.0-alpha.7] - 2026-05-03

### Added
- Introduced better logging for debugging, offering a `pw_debug()` function and creating log-files
- Added log viewer and basic controls to the general settings page
- Added API endpoints to work with the debug log
- Added tags-macro and template variable to display tags on the rendered page

## [0.2.0-alpha.6] - 2026-04-25

### Added
**Extension System**: 
- Introduced a hook-based API for custom plugins without modifying core files.
- Added support for extensions to inject CSS/JS assets, manipulate frontend HTML, and register custom Editor.js blocks.
- Extensions can add custom fields to Page Settings and Global Setting.
- Added API routing support allowing extensions to create secure backend endpoints.
- Added Extension Manager in the Admin Dashboard to activate, deactivate, and uninstall extensions.
- Provided an official `example-extension` repository: [https://github.com/purewiki/example-extension](https://github.com/purewiki/example-extension)

## [0.2.0-alpha.5] - 2026-04-23

### Added
- Include markdown headings in TOC generation for the final page (not supported in editor TOC preview)
- Add warning on page reload or closing browser tab while update is running

### Changed
- Improve page picker sorting by including page title for better search results
- Minor code refactoring

## [0.2.0-alpha.4] - 2026-04-19

### Added
- Add trash management to restore and permanently delete pages
- Raw HTML support for dialogs
- Add button to show full release notes formatted in dialog

### Changed
- Modified strings in the update section
- Changelog button link now opens the GitHub releases page

## [0.2.0-alpha.3] - 2026-04-16

### Added
- Add hidden block tune to hide blocks from the rendered page.

### Fixed
- Missing CSS variables in core.css (--pw-success, --pw-warning, --pw-danger)

## [0.2.0-alpha.2] - 2026-04-11

### Added
- Implement BASE_PATH in links for subdirectory and reverse-proxy deployments
- Make PureWiki path-agnostic

## [0.2.0-alpha.1] - 2026-04-06

### Added
- Allow system updates from GitHub pre-releases
- Dependency management for external libraries
- GitHub Actions for dependency version checking and automatic release asset creation

### Fixed
- Fixed not working updater

### Changed
- Switched PHPMailer to Version 7.0.2

## [0.1.0] - 2026-03-30

### Added
- Initial public release of PureWiki.
- Core wiki functionality with page management.
- Backend admin dashboard for settings and content editing.
- Theme system (default theme included).
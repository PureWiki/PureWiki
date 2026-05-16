# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
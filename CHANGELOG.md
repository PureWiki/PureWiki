# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [unreleased]

### Added
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
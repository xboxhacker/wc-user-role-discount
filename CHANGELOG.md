# Changelog

## [1.3.0] - 2025-01-04
### Changed
- Update version to 1.3.0
- Added author information to plugin header

## [1.2.8] - 2025-01-04
### Changed
- Update version to 1.2.8

## [1.2.7] - 2025-01-04
### Added
- Added admin menu for managing role-based discounts
- Added settings page for configuring discounts per user role
- Added functionality to add and delete user roles
- Applied discounts based on user roles in WooCommerce cart

### Changed
- Ensured WordPress functions are available before execution
- Used `plugins_loaded` hook to initialize plugin
- Registered settings for each user role

### Fixed
- Ensured `wp-load.php` is loaded only if not already loaded

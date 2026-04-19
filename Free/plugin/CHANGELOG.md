# Changelog

## 1.0.3 - 2025-08-26
- Fixed cache issues, UpgradePrompt error, and improved API error handling


## 1.0.2 - 2025-08-26
- Fixed cache issues and UpgradePrompt error




















## 1.0.11 - 2025-08-25
- 100% WordPress.org COMPLIANT - All requirements met





## 1.0.10 - 2025-08-25
- WordPress.org COMPLIANT - All requirements met














## 1.0.9 - 2025-08-24
- MAJOR UPDATE - Removed Automation, Advanced Debug, and License pages from Free version for cleaner codebase





## 1.0.8 - 2025-08-24
- ACTIVATION FIX - Fixed WordPress version check and activation context issues


## 1.0.7 - 2025-08-24
- DEEP FIX - Fixed ACA_Cache_Manager reference, file paths, global variable issues


## 1.0.6 - 2025-08-24
- COMPLETE FIX - Added safety checks for all critical operations


## 1.0.5 - 2025-08-24
- CRITICAL FIX - Removed non-existent class references and fixed initialization order


## 1.0.4 - 2025-08-24
- FINAL RELEASE - All critical errors fixed, dual-asset support enabled




















All notable changes to AI Content Agent (ACA) will be documented in this file.

## [1.0.1] - 2024-08-18

### Fixed
- **WordPress.org Compliance**: Fixed prefix naming issue - changed `AI_Content_Agent` class to `ACA_Content_Agent`
- **Automation System**: 
  - Corrected SQL query for tracking idea-to-draft relationships (now uses `_aca_created_from_idea` meta key)
  - Added semi-automatic mode support to draft creation function
  - Added fallback mechanism for finding unpublished drafts
  - Improved error handling and debug logging throughout automation workflows

### Changed
- Updated all global variable references from `$ai_content_agent` to `$aca_content_agent`
- Enhanced automation system reliability with multiple fallback mechanisms
- Improved compatibility with both semi-automatic and full-automatic modes

### Technical
- Fixed `get_latest_unprocessed_idea_id()` to use correct postmeta relationship
- Enhanced `get_latest_unpublished_draft_id()` with two-stage search
- Added fallback to find any active idea when no unprocessed ideas exist

## [1.0.0] - 2024-08-11

### Initial Release
- **Core Features**:
  - AI-powered content generation using Google Gemini API
  - Style guide analysis and content matching
  - Idea generation and management system
  - Draft creation from ideas
  - SEO optimization with meta titles and descriptions
  
- **Automation**:
  - Manual mode for full control
  - Semi-automatic mode for assisted workflow (Pro)
  - Full-automatic mode for hands-free operation (Pro)
  
- **Admin Interface**:
  - React-based modern dashboard
  - Real-time content management
  - Bulk operations support
  - Activity logging and tracking
  
- **Integrations**:
  - WordPress native REST API
  - Support for Yoast SEO
  - Support for RankMath
  - Support for All in One SEO
  
- **Pro Features**:
  - Advanced automation modes
  - Priority support
  - Extended API limits

### Notes
- Requires WordPress 5.0+
- Requires PHP 7.4+
- Initial stable release for WordPress.org repository




























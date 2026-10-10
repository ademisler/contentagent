# Changelog

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
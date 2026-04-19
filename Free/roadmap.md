# Roadmap: WordPress.org Plugin Compliance - Complete Removal of Licensing and Pro Features

## Executive Summary
After thorough analysis of the Free folder, I've identified **25 files** that need modification to remove all licensing, pro features, and fix prefix issues. The plugin contains extensive Gumroad licensing integration, multiple pro-only features, and uses a non-compliant 3-character prefix "aca" that must be changed.

## Detailed Analysis Results

### 1. License System Components Found

#### PHP Files with License Code
1. **`/plugin/ai-content-agent.php`**
   - Contains `is_aca_pro_active()` function (lines 35-75)
   - License verification logic with cache management
   - Pro status passed to JavaScript via `acaData`

2. **`/plugin/includes/class-aca-rest-api.php`**
   - Gumroad API integration (lines 4769-4900)
   - License endpoints: verify, status, deactivate, refresh (lines 334-352)
   - `check_pro_permissions()` method (line 448)
   - Pro status checks in multiple methods

3. **`/plugin/includes/class-aca-simple-automation.php`**
   - Pro license checks for semi/full-automatic modes (lines 124, 148, 156, 177, 188, 434-437)
   - Mode restrictions based on license status
   - License status in debug output

4. **`/plugin/includes/class-aca-cache-manager.php`**
   - License cache triggers (lines 18-19, 92-95)

5. **`/plugin/includes/class-aca-activator.php`**
   - License option initialization

6. **`/plugin/uninstall.php`**
   - License option cleanup (lines 60-64)

#### React/TypeScript Components with Pro Features
1. **`/plugin/components/SettingsLicense.tsx`** - ENTIRE FILE TO DELETE (232 lines)
   - Complete license management UI
   - Gumroad purchase link

2. **`/plugin/components/UpgradePrompt.tsx`** - ENTIRE FILE TO DELETE (124 lines)
   - Pro upgrade prompts
   - Gumroad purchase links

3. **`/plugin/components/SimpleAutomationManager.tsx`**
   - Semi-automatic mode references (lines 22-23, 128-130, 274-276, 351-353, 401-403, 636-649, 816-817, 1080-1081, 1112-1114)
   - Full-automatic mode references (lines 120-125, 266-271, 355-358, 650-653, 844-862, 962-963, 1086-1110)

4. **`/plugin/components/SettingsAutomation.tsx`**
   - UpgradePrompt usage (lines 5, 204-213)
   - Pro license checks (lines 123-126, 129, 137-148, 197-198)
   - Semi/full-automatic mode UI (lines 252-281, 318-396)

5. **`/plugin/components/SettingsContent.tsx`**
   - UpgradePrompt import (line 5)
   - isProActive prop (lines 13, 31)
   - Character count limits (pro feature to remove)

6. **`/plugin/components/SettingsAdvanced.tsx`**
   - UpgradePrompt usage (lines 4, 129-140)
   - Pro-only debug features (line 122)

7. **`/plugin/App.tsx`**
   - SettingsLicense import and routing (lines 13, 584-593)
   - Pro status passing to components (lines 608, 615, 620)

8. **`/plugin/services/wordpressApi.ts`**
   - License API implementation (lines 169-199)

### 2. Pro Features to Remove

#### Automation Features
- **Semi-automatic mode**: AI generates ideas automatically, user reviews
- **Full-automatic mode**: Complete automation pipeline
- Both modes require pro license checks throughout the codebase

#### Content Features
- **Character count customization**: Currently pro-only in SettingsContent
- **Advanced debug page**: Entire SettingsAdvanced component is pro-only

#### UI Components
- License management tab
- Upgrade prompts throughout the interface
- Pro badges and indicators

### 3. External Services Needing Documentation

Currently undocumented in readme.txt:
1. **Google Generative Language API** (Gemini)
   - Used for content generation
   - Found in: class-aca-rest-api.php line 3517
   - URL: https://generativelanguage.googleapis.com/v1beta/models/

2. **Pexels API**
   - Used for featured images
   - Found in: class-aca-rest-api.php line 3397
   - URL: https://api.pexels.com/v1/search

3. **Gumroad API** (TO BE REMOVED)
   - Used for license verification
   - Found in: class-aca-rest-api.php line 4772
   - URL: https://api.gumroad.com/v2/licenses/verify

### 4. Prefix Issues

**Current prefix**: `aca` (3 characters)
**Required prefix**: Minimum 4 characters
**Suggested new prefix**: `aicoagac` (8 characters, based on plugin name)

#### Occurrences to change:
- **PHP Constants**: `ACA_VERSION`, `ACA_PLUGIN_URL`, `ACA_PLUGIN_PATH`
- **PHP Classes**: `ACA_Content_Agent`, `ACA_Rest_Api`, `ACA_Simple_Automation`, `ACA_Cache_Manager`, `ACA_Activator`, `ACA_Deactivator`, `ACA_Migration_Manager`
- **Database Options**: 50+ options starting with `aca_`
- **REST API Namespace**: `aca/v1` → `aicoagac/v1`
- **JavaScript Object**: `acaData` → `aicoagacData`
- **Hooks**: All `aca_` prefixed hooks
- **CSS Classes**: `aca-` prefixed classes (1400+ in index.css)

### 5. Database Options to Remove/Rename

#### License-related options to DELETE:
- `aca_license_status`
- `aca_license_key`
- `aca_license_verified`
- `aca_license_timestamp`
- `aca_license_data`
- `aca_license_site_hash`

#### Options to RENAME (prefix change):
- `aca_settings` → `aicoagac_settings`
- `aca_style_guide` → `aicoagac_style_guide`
- `aca_google_cloud_project_id` → `aicoagac_google_cloud_project_id`
- `aca_google_cloud_location` → `aicoagac_google_cloud_location`
- `aca_last_cron_run` → `aicoagac_last_cron_run`
- `aca_last_automation_run` → `aicoagac_last_automation_run`
- `aca_last_scheduled_settings_hash` → `aicoagac_last_scheduled_settings_hash`
- `aca_index_backfill_done` → `aicoagac_index_backfill_done`
- `aca_index_backfill_last_id` → `aicoagac_index_backfill_last_id`
- `aca_assets_version` → `aicoagac_assets_version`
- `aca_last_cache_clear` → `aicoagac_last_cache_clear`
- `aca_migration_timestamp` → `aicoagac_migration_timestamp`
- `aca_automation_critical_failure` → `aicoagac_automation_critical_failure`
- `aca_last_maintenance_run` → `aicoagac_last_maintenance_run`
- `aca_db_version` → `aicoagac_db_version`
- `aca_migration_log` → `aicoagac_migration_log`

#### Transients to RENAME:
- All `aca_*` transients to `aicoagac_*`

### 6. REST API Endpoints to Remove

- `/aca/v1/license/verify`
- `/aca/v1/license/status`
- `/aca/v1/license/deactivate`
- `/aca/v1/license/refresh`
- `/aca/v1/debug/pro-status`

### 7. Files Requiring Major Changes

#### Files to DELETE completely:
1. `/plugin/components/SettingsLicense.tsx` (232 lines)
2. `/plugin/components/UpgradePrompt.tsx` (124 lines)

#### Files requiring extensive modification (25 total):
1. `/plugin/ai-content-agent.php` - Remove license function, change prefix
2. `/plugin/includes/class-aca-rest-api.php` - Remove license methods, change prefix
3. `/plugin/includes/class-aca-simple-automation.php` - Remove pro modes, change prefix
4. `/plugin/includes/class-aca-cache-manager.php` - Remove license triggers, change prefix
5. `/plugin/includes/class-aca-activator.php` - Remove license init, change prefix
6. `/plugin/includes/class-aca-deactivator.php` - Change prefix
7. `/plugin/includes/class-aca-migration-manager.php` - Add migration, change prefix
8. `/plugin/uninstall.php` - Update option names
9. `/plugin/App.tsx` - Remove license tab, update API calls
10. `/plugin/components/SimpleAutomationManager.tsx` - Remove pro modes
11. `/plugin/components/SettingsAutomation.tsx` - Remove pro UI
12. `/plugin/components/SettingsContent.tsx` - Remove pro limits
13. `/plugin/components/SettingsAdvanced.tsx` - Remove pro restriction
14. `/plugin/components/Dashboard.tsx` - Remove pro indicators
15. `/plugin/components/IdeaBoard.tsx` - Check for pro references
16. `/plugin/components/DraftsList.tsx` - Check for pro references
17. `/plugin/components/PublishedList.tsx` - Check for pro references
18. `/plugin/components/ContentCalendar.tsx` - Check for pro references
19. `/plugin/components/ActivityLog.tsx` - Check for pro references
20. `/plugin/components/Sidebar.tsx` - Remove license menu item
21. `/plugin/services/wordpressApi.ts` - Remove license API, update endpoints
22. `/plugin/index.tsx` - Update API endpoint references
23. `/plugin/index.css` - Change all aca- prefixes
24. `/plugin/readme.txt` - Add external services documentation
25. `/plugin/admin/js/index.js` - Update API endpoints

## Implementation Plan

### Phase 1: Remove License System (2 hours)
1. Delete `SettingsLicense.tsx` and `UpgradePrompt.tsx`
2. Remove `is_aca_pro_active()` function from `ai-content-agent.php`
3. Remove all license REST endpoints from `class-aca-rest-api.php`
4. Remove license API from `wordpressApi.ts`
5. Remove license tab from `App.tsx`
6. Remove all UpgradePrompt imports and usage

### Phase 2: Remove Pro Features (2 hours)
1. Remove semi-automatic and full-automatic modes from:
   - `class-aca-simple-automation.php`
   - `SimpleAutomationManager.tsx`
   - `SettingsAutomation.tsx`
2. Remove character count customization from `SettingsContent.tsx`
3. Remove pro restriction from `SettingsAdvanced.tsx`
4. Remove all pro status checks and conditionals

### Phase 3: Fix Prefix Issues (2 hours)
1. Global search and replace:
   - `aca_` → `aicoagac_`
   - `ACA_` → `AICOAGAC_`
   - `class ACA` → `class AICOAGAC`
   - `aca/v1` → `aicoagac/v1`
   - `acaData` → `aicoagacData`
   - CSS: `aca-` → `aicoagac-`
2. Update all file names (class-aca-*.php)
3. Update database migration to rename old options

### Phase 4: Document External Services (30 minutes)
1. Add "External Services" section to readme.txt
2. Document Google Generative Language API
3. Document Pexels API
4. Include privacy policy links

### Phase 5: Testing and Validation (1 hour)
1. Test plugin activation
2. Verify all features work without pro checks
3. Check WP_DEBUG for errors
4. Run Plugin Check tool
5. Verify prefix compliance

## Risk Assessment

### High Risk Areas:
1. **Database migration**: Need careful handling of option renaming
2. **CSS changes**: 1400+ class references need updating
3. **JavaScript API calls**: All endpoints need updating
4. **Cron jobs**: Hook names changing may affect scheduled tasks

### Mitigation Strategies:
1. Create database migration to handle option renaming
2. Use find/replace carefully with regex for CSS
3. Test all AJAX calls after endpoint changes
4. Clear and reschedule all cron jobs on activation

## Success Metrics
- [ ] Zero references to Gumroad API
- [ ] Zero references to license verification
- [ ] Zero pro/premium conditional features
- [ ] All prefixes are 4+ characters
- [ ] External services documented in readme.txt
- [ ] Plugin activates without errors
- [ ] All features work without payment
- [ ] WP_DEBUG shows no errors
- [ ] Plugin Check tool passes

## Estimated Timeline
- **Total time**: 7-8 hours
- Phase 1: 2 hours
- Phase 2: 2 hours  
- Phase 3: 2 hours
- Phase 4: 30 minutes
- Phase 5: 1-1.5 hours

## Next Steps
1. Backup the current plugin state
2. Create a test environment
3. Begin systematic implementation following this roadmap
4. Test thoroughly after each phase
5. Final validation before submission
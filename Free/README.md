# AI Content Agent (ACA) – Free Version

## 🚀 WordPress.org Compliant Version

This is the **FREE** version of AI Content Agent, fully compliant with WordPress.org guidelines.

### ✅ Key Changes from Pro Version:
- **No licensing system** - All features are free
- **No Gumroad integration** - Removed all payment/license checks
- **Prefix compliance** - Changed from `aca` (3 chars) to `aicoagac` (8 chars)
- **External services documented** - Google Generative Language API and Pexels API properly documented
- **Clean codebase** - No console.log, no debug code in production

## 📁 Directory Structure

- `plugin/` – WordPress plugin (stable tag: trunk)
  - `ai-content-agent.php` – Main plugin file
  - `admin/` – Compiled admin assets
  - `includes/` – PHP classes (all with `aicoagac_` prefix)
  - `components/` – React/TypeScript components
  - `services/` – API service handlers
  - `languages/` – Translation files
  - `readme.txt` – WordPress.org readme

- `scripts/` – Build and deployment scripts
- `release/` – Distribution packages
- `assets/` – WordPress.org assets (banners, icons)

## 🛠️ Development

### Requirements
- PHP 7.4+
- Node.js 18+
- npm or yarn

### Setup
```bash
# Install dependencies
cd plugin
npm install

# Build for production
npm run build

# Development mode
npm run dev
```

### Build Commands
```bash
# Build and copy to admin folder
npm run build:wp

# Generate POT file
npm run make-pot

# Create release package
npm run release:min --version=1.0.1
```

## 🔧 PHP Syntax Check
```bash
# Check all PHP files
for file in $(find plugin -name "*.php"); do
  php -l "$file"
done
```

## 📋 WordPress.org Compliance

### ✅ Completed Requirements:
1. **Prefix**: All functions, classes, and options use `aicoagac_` prefix (8 characters)
2. **External Services**: Documented in readme.txt
   - Google Generative Language API (Gemini)
   - Pexels API
3. **No Premium Features**: All features are free
4. **No License Checks**: Completely removed
5. **Clean Code**: No debug statements in production

### 🔍 Verification Commands:
```bash
# Check for old prefixes
grep -r "aca_" plugin --include="*.php"

# Check for Gumroad references
grep -ri "gumroad" plugin

# Check for console.log
grep -r "console.log" plugin --include="*.tsx" --include="*.ts"

# Check PHP syntax
php -l plugin/ai-content-agent.php
```

## 📦 Distribution

### Creating Release Package:
```bash
# From root directory
npm run release:min --version=1.0.1

# Output: release/ai-content-agent-1.0.1-min.zip
```

### Files Excluded from Distribution:
- `node_modules/`
- `*.tsx`, `*.ts` (source files)
- `.git/`, `.github/`
- Development configs
- Test files

## 🚀 Deployment to WordPress.org

1. **Prepare SVN checkout:**
```bash
svn co https://plugins.svn.wordpress.org/ai-content-agent svn-repo
```

2. **Deploy to SVN:**
```bash
SVN_DIR=/path/to/svn-repo npm run deploy:svn
```

3. **Commit changes:**
```bash
cd svn-repo
svn commit -m "Version 1.0.1 - WordPress.org compliance update"
```

## 📝 Changelog

### Version 1.0.1
- ✅ Removed all Pro/licensing features
- ✅ Changed prefix from `aca_` to `aicoagac_`
- ✅ Documented external services
- ✅ Fixed all PHP syntax errors
- ✅ Cleaned up debug code
- ✅ Made all features free

## 🔒 Security

- No `eval()` usage
- No hardcoded API keys
- Proper nonce verification
- Sanitized inputs/outputs
- SQL prepared statements

## 📄 License

GPL v2 or later

## 👨‍💻 Author

Adem Isler - [https://ademisler.com](https://ademisler.com)

## 🆘 Support

For issues and questions, please use the WordPress.org support forum.

---

**Note:** This is the FREE version. All Pro features have been removed and all functionality is available without payment.
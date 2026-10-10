=== AI Content Agent (ACA) ===
Contributors: ademisler
Donate link: https://buymeacoffee.com/ademisler
Tags: ai, content, automation, seo, writer
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plan, produce, and schedule content with an integrated, WordPress‑native AI workflow. From idea to publish—streamlined and automated.

== Description ==

AI Content Agent (ACA) delivers a complete content workflow designed for WordPress:
from ideation, to draft creation, to scheduling and publishing—without leaving your dashboard.

- Unified UI (React + TypeScript), purpose‑built for content teams
- Idea generation using Google Gemini with site‑aware prompts
- Draft composer with SEO‑friendly structure and featured image support
- WordPress‑native scheduling via cron for reliable automation
- Works alongside SEO plugins (Yoast, Rank Math, AIOSEO) with safe fallbacks
- Privacy‑by‑default. No telemetry. No hidden tracking.

= Why ACA? Our Positioning =

- AI Power: feature‑rich but complex; ACA focuses on a smooth, opinionated workflow.
- Rank Math & Yoast Content AI: bundled with SEO suites and extra subscriptions; ACA specializes in end‑to‑end content flow.
- Other GPT wrappers: often just a text box; ACA is a full pipeline—ideate → draft → schedule.

= Key Features =

- AI‑Powered Ideation
  - Generate topic ideas aligned with your site language and categories
  - Control tone, creativity, and output length

- Draft & Edit
  - Create structured drafts with headings, keywords, and featured images
  - Review and refine before publishing

- Scheduling & Automation
  - Visual calendar in the WP Admin
  - Manual, assisted, and automatic modes
  - Reliable WordPress‑native cron

- Integrations & Compatibility
  - Plays well with Yoast SEO, Rank Math, and AIOSEO
  - JSON‑LD FAQ rendering with deduplication safeguards

- Developer‑Friendly
  - Clean PHP backend, modern React frontend
  - REST API endpoints following WP standards
  - Translation‑ready with `.pot` and `/languages`

= Pro Version Available =

Want even more powerful features? The Pro version includes:

- **Full Automation System**: Set it and forget it - automatically generate, draft, and publish content
- **Longer Content Generation**: Create comprehensive articles up to 5000+ words
- **Semi-Auto & Full-Auto Modes**: Choose your level of automation control
- **Auto-Publishing**: Schedule and publish content automatically without manual intervention
- **Advanced SEO Integration**: Enhanced compatibility with Yoast, RankMath, and AIOSEO Pro features
- **Bulk Operations**: Generate multiple ideas and drafts at once
- **License Management**: Activate and manage your Pro license
- **Priority Support**: Get help directly from the development team

Learn more about the Pro version at: https://ademisler.gumroad.com/l/ai-content-agent-pro

== Installation ==

= Automatic Installation =
1. Go to Plugins → Add New, search for "AI Content Agent"
2. Install and Activate
3. Open AI Content Agent from the admin menu

= Manual Installation =
1. Upload the folder to `/wp-content/plugins/ai-content-agent/`
2. Activate the plugin via Plugins screen
3. Open AI Content Agent from the admin menu

== Frequently Asked Questions ==

= Is the plugin free to use? =
Yes. All features are completely free to use.

= Do I need an API key? =
Yes. You'll need a Google Gemini API key. Add it under Settings → Integrations.

= Does it work with my SEO plugin? =
Yes. ACA is compatible with Yoast SEO, Rank Math, and AIOSEO. It uses safe fallbacks when third‑party fields aren't available.

= How does scheduling work? =
ACA uses WordPress‑native cron. For low‑traffic sites, set up a system cron to ping `wp-cron.php` for precise timing.

= Is there page view tracking? =
No. ACA does not track visitors by default. We removed automatic view counting to prioritize privacy. If you need it, use a dedicated analytics plugin.

= GDPR and data residency? =
ACA does not collect personal data. Content you choose to send to Google Gemini is transmitted over HTTPS and is only used to generate outputs. No telemetry is collected by ACA.

= Multisite support? =
Yes. Activate per site. Each site has its own settings and schedules.

= Can I export or remove data? =
Yes. You can uninstall and opt to remove all plugin data (options, schedules, related metadata) or preserve it.

== External Services ==

This plugin connects to external services to provide its functionality. Here's what data is sent and when:

= Google Generative Language API (Gemini) =
* **Purpose**: Generate AI content including ideas, drafts, meta descriptions, and FAQs
* **When**: Only when you actively use AI generation features (clicking generate buttons)
* **Data sent**: Your content prompts, existing post titles (for context), language preferences
* **Service URL**: https://generativelanguage.googleapis.com/v1beta/models/
* **Privacy Policy**: https://policies.google.com/privacy
* **Terms of Service**: https://policies.google.com/terms

= Pexels API =
* **Purpose**: Fetch royalty-free featured images for your content
* **When**: When you generate content with the "Include featured image" option enabled and Pexels is selected as image source
* **Data sent**: Search query based on your content topic
* **Service URL**: https://api.pexels.com/v1/search
* **Privacy Policy**: https://www.pexels.com/privacy-policy/
* **Terms of Service**: https://www.pexels.com/terms-of-service/

= Unsplash API (Optional) =
* **Purpose**: Fetch royalty-free featured images for your content
* **When**: When you generate content with the "Include featured image" option enabled and Unsplash is selected as image source
* **Data sent**: Search query based on your content topic
* **Service URL**: https://api.unsplash.com/search/photos
* **Privacy Policy**: https://unsplash.com/privacy
* **Terms of Service**: https://unsplash.com/terms

= Pixabay API (Optional) =
* **Purpose**: Fetch royalty-free featured images for your content
* **When**: When you generate content with the "Include featured image" option enabled and Pixabay is selected as image source
* **Data sent**: Search query based on your content topic
* **Service URL**: https://pixabay.com/api/
* **Privacy Policy**: https://pixabay.com/service/privacy/
* **Terms of Service**: https://pixabay.com/service/terms/

== Privacy Policy ==

- No telemetry. No hidden tracking.
- No personal data is transmitted unless you explicitly include it in prompts.
- External API calls: See External Services section above for details.
- Uninstall options let you delete all plugin data.

== Screenshots ==
1. Dashboard – quick actions and status
2. Idea Board – AI‑generated topics with filters
3. Content Calendar – plan and schedule at a glance
4. Draft Editor – refine content before publishing
5. Settings – integrations, AI options, workflow controls

== Changelog ==

= 1.0.3 =
* Fixed cache-related issues with API settings
* Fixed UpgradePrompt component loading error
* Improved error handling for API calls

= 1.0.2 =
* WordPress.org compliance improvements

= 1.0.1 =
* Initial release

== Upgrade Notice ==
= 1.0.3 =
Important bug fixes for API settings and component loading.
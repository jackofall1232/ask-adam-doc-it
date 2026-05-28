=== Ask Adam Doc It ===
Contributors: jackofall1232
Tags: document library, file manager, AI search, PDF, OpenAI
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free WordPress document library with optional AI semantic search via your own OpenAI API key. Part of the Ask Adam suite.

== Description ==

Ask Adam Doc It is a free-forever document library for WordPress. Upload PDFs, Word documents, spreadsheets, images, and any other file types your media library accepts, then organize them with categories and surface them with a shortcode, a Gutenberg block, or built-in archive templates.

Drop in your own OpenAI API key to unlock semantic search — find documents by meaning, not just keywords. The plugin works fully without an API key; AI is purely optional.

Ask Adam Doc It is part of the **Ask Adam** suite by [askadamit.com](https://askadamit.com). The free plugin is feature-complete on its own. If you want conversational document Q&A, multi-document context retrieval, bulk indexing, and analytics, those live in Ask Adam Pro at [askadamit.com/purchase](https://askadamit.com/purchase) — a separate product.

= What's included (free) =

* Dedicated "Files" custom post type for documents
* Categories and tags for organizing your library
* Frontend archive and single views with theme-overridable templates
* `[ask_adam_doc_it]` shortcode and a matching Gutenberg block
* Classic keyword search across your library
* Optional AI-powered semantic search via your own OpenAI API key
* Admin columns and meta boxes for quick file management
* Clean uninstall with an opt-in "delete my data" switch

There are no feature locks, license checks, or paid upgrades inside this plugin.

== External Services ==

When an OpenAI API key is provided in Settings, Ask Adam Doc It sends document summary text to the OpenAI API to generate search embeddings.

**What data is sent:**

* *Document metadata (admin-controlled):* The document title, excerpt, AI Search Summary field, and category names for each published document. The plugin does not intentionally collect or transmit WordPress account, profile, or visitor data, but because these fields are authored by site administrators, any personal information an admin types into them (for example, a person's name in a document title) will be sent to OpenAI as part of the embedding source text.
* *Visitor search text (visitor-controlled):* When a visitor performs an AI search, the raw search query they typed is sent to OpenAI to generate a query embedding for semantic matching. The plugin cannot inspect this text in advance, so if visitors enter personal information (names, email addresses, etc.) it will be transmitted to OpenAI as part of the query. No IP address, user ID, cookie, or other identifier is attached to either request.

**When data is sent:** Document metadata is sent when a document is published or updated (via WP-Cron, a few seconds after save), or when an admin manually regenerates an embedding. Visitor search text is sent each time a visitor submits an AI-mode search.

**This feature is entirely optional.** The plugin works fully without an API key using keyword search.

**Service:** OpenAI API (api.openai.com)
**Privacy Policy:** [https://openai.com/privacy](https://openai.com/privacy)
**Terms of Service:** [https://openai.com/terms](https://openai.com/terms)

No data is sent to any service when no API key is configured.

== Installation ==

1. Upload the `ask-adam-doc-it` folder to `/wp-content/plugins/`, or install via the Plugins screen in WordPress.
2. Activate **Ask Adam Doc It** through the Plugins screen.
3. (Optional) Visit **Files → Settings** and paste your OpenAI API key to enable semantic search.
4. Add your first document under **Files → Add New**.
5. Embed the library anywhere with the `[ask_adam_doc_it]` shortcode or the Ask Adam Doc It block.

== Frequently Asked Questions ==

= Does it work without an OpenAI API key? =

Yes. The plugin is fully functional with zero configuration. Without an API key you get a complete document library with classic keyword search. Adding an API key only enables the optional AI semantic search layer.

= Is this really free? =

Yes. Ask Adam Doc It is GPLv2-licensed and 100% free. There are no premium feature locks, no license keys, and no upsells embedded in functionality. The only paid product mentioned anywhere is a passive sidebar link to Ask Adam Pro, which is a completely separate product you can ignore.

= How is it different from Ask Adam Pro? =

Ask Adam Doc It is a self-contained WordPress document library that you host and control. Ask Adam Pro (sold separately at askadamit.com/purchase) is a broader product that adds conversational document Q&A, multi-document context retrieval with citations, bulk embedding tools, analytics, and priority support. The two work well together but neither requires the other.

= What file types are supported? =

Any file type your WordPress media library accepts can be attached to an Ask Adam Doc It file entry, including PDFs, Word documents (.doc/.docx), spreadsheets (.xls/.xlsx/.csv), presentations (.ppt/.pptx), images (.jpg/.png/.gif/.webp), and plain text files. You can extend supported MIME types using the standard `upload_mimes` WordPress filter. SVG is intentionally excluded — it is a stored XSS vector. SVG support requires dedicated sanitization — available in Pro.

= Is Ask Adam Doc It available in my language? =

Ask Adam Doc It is translation-ready. A .pot file is included in the languages/ folder. If you would like to contribute a translation, please get in touch via https://askadamit.com/contact.

== Screenshots ==

1. The document library admin list view with file type, size, and download statistics columns.
2. The settings page with optional OpenAI API key and upload size configuration.
3. The frontend document library shortcode output with search and AI-mode results.

== Changelog ==

= 1.1.0 =
* New: Optional AI Summarize button on document cards and single document pages. When enabled in Settings, visitors can request a plain-English 2-3 sentence summary of any document on demand before downloading.
* Summaries are generated via OpenAI GPT and cached for one week per document — repeat clicks serve instantly from cache with no additional API cost.
* Rate limited to 30 fresh generations per hour site-wide to prevent API abuse.
* Button hidden automatically when AI is disabled or the OpenAI circuit breaker is tripped.
* Password-protected documents cannot be summarized by anonymous visitors.

= 1.0.1 =
* Branded admin UI with teal hero, tabbed settings, and help documentation tab.

= 1.0.0 =
* Initial release.
* `aadi_file` custom post type and `aadi_category` taxonomy.
* Classic keyword search across the document library.
* Optional AI semantic search via OpenAI embeddings (bring your own API key).
* `[ask_adam_doc_it]` shortcode and matching Gutenberg block.
* Admin meta boxes, list-table columns, and settings page.
* Theme-overridable archive and single templates.
* Opt-in clean uninstall.

== Upgrade Notice ==

= 1.1.0 =
Adds optional AI document summarization. Enable it under Settings → AI Configuration after upgrading.

= 1.0.1 =
Refreshed admin settings page with branded teal hero, tabbed layout, and help tab.

= 1.0.0 =
Initial release.

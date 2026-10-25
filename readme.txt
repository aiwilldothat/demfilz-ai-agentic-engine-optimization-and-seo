=== Demfilz AI Agentic Engine Optimization and SEO ===
Contributors: demfilz
Tags: seo, llms.txt, faq schema, sitemap, ai
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SEO plus AEO: meta titles and descriptions, FAQ schema, XML sitemap, llms.txt with markdown mirrors for AI assistants, and AI crawler controls.

== Description ==

Search engines are no longer the only readers of your website. AI assistants like ChatGPT, Claude and Perplexity now answer questions about your business every day, and they answer best when your site gives them clean, structured, quotable content. Demfilz AI Agentic Engine Optimization and SEO covers both audiences in one plugin:

= Classic SEO =

* **Meta titles and descriptions** on posts, pages and products, with live character counters and a search snippet preview in the editor.
* **Canonical URL** override and a per-page **noindex** toggle.
* **Open Graph and Twitter card tags** with a site-wide default social image.
* **XML sitemap** at /sitemap.xml, advertised in robots.txt (replaces the core wp-sitemap while enabled).
* **Schema.org JSON-LD**: Organization and WebSite (with SearchAction) on the front page, Article on posts, BreadcrumbList on singular content.
* **Breadcrumbs** via the `[daeo_breadcrumbs]` shortcode, matching the schema.

= Agentic engine optimization (AEO) =

* **llms.txt** at /llms.txt: a curated, always-current index of your key pages in the llms.txt convention AI assistants read. Pin key pages to the top or exclude any page, per page.
* **llms-full.txt**: your key content concatenated as clean Markdown for one-request ingestion.
* **Markdown page mirrors**: every page also available as clean Markdown at its URL plus .md (advertised with a rel=alternate link), so AI crawlers read your content without the theme noise. Mirrors render content exactly as a public visitor sees it, so membership and paywall restrictions are honoured.
* **FAQ manager with schema**: add questions and answers to any page, show them as an accessible accordion (shortcode or auto-append), and publish FAQPage JSON-LD, the content AI assistants quote most.
* **AI crawler controls**: allow or block GPTBot, ClaudeBot, PerplexityBot, Google-Extended, CCBot, Bytespider and more from robots.txt, with a one-click "block all training bots" preset.

= Optimization suggestions =

The Suggestions dashboard scores every published page 0-100 against practical checks: meta title and description present and well-sized, content length, subheading structure, duplicate H1s, image alt text, internal links, featured image and FAQ presence, and tells you exactly what to fix. Sort by worst score first to work through the weakest pages.

= Works with your setup =

Every module can be switched off individually, so the plugin coexists with other SEO plugins (it warns you when one is active). Switching over? One click imports your existing per-page meta titles and descriptions from Yoast SEO, Rank Math, All in One SEO or SEOPress. WooCommerce is optional: product support lights up automatically.

= Pro version =

Everything above is free and fully functional. For sites that want an AI copywriter inside WordPress, [AI SEO Pro](https://demfilz.com/ai-seo) is a separate add-on plugin that adds: one-click AI meta titles and descriptions, bulk generation for everything missing metas, AI-drafted FAQ items from your page content, an AI Writer that drafts complete pages (content, meta and FAQ) from a topic, and AI page advice in the dashboard. Pro is $19.95 per month with 200 AI generations included, or Pro Plus at $39.95 per month with 1,000, and you can bring your own Anthropic API key instead.

== External services ==

This plugin does not connect to any external service. No data leaves your site.

== Installation ==

1. Install and activate the plugin.
2. Go to **AI SEO -> Settings**, check the enabled modules and fill in your site identity (organization name, logo, profiles, site summary).
3. Edit any page, post or product and fill in the **AI SEO** box (meta title and description) and the **FAQ** box.
4. Open **AI SEO -> Suggestions** to see what to improve next.
5. Visit /sitemap.xml, /llms.txt and /robots.txt on your site to see the generated files.

== Frequently Asked Questions ==

= What is agentic engine optimization? =

Optimizing your site for AI assistants and their crawlers, not just search engines: publishing llms.txt, offering clean Markdown versions of pages, using FAQPage and Organization schema, and controlling which AI bots may read or train on your content. AI assistants increasingly answer questions people used to type into Google; AEO makes sure they describe your business accurately.

= Where do llms.txt and the markdown mirrors come from? =

They are generated on the fly from your published content; nothing is written to disk. llms.txt lists your pages with one-line summaries (the meta description or excerpt when set), pinned pages first. Each page is also served as Markdown at its URL plus .md, for example /about/ becomes /about.md, and a ?daeo_md=ID form works on sites without pretty permalinks. Content is rendered the way a logged-out visitor sees it, so membership or paywall plugins keep protecting their content, and any page can be excluded in its AI SEO box.

= Will it clash with Yoast, Rank Math or another SEO plugin? =

The plugin warns you when one is active, and every module (meta output, social tags, schema, sitemap, llms.txt, markdown mirrors) has its own on/off switch, so you can run just the AEO features next to your existing SEO plugin.

= Does the FAQ output require a page builder or block? =

No. Add questions and answers in the FAQ box on any page, then either tick "append automatically" or place the `[daeo_faq]` shortcode where you want the accordion. FAQPage JSON-LD is emitted whenever a page has FAQ items.

= Which AI crawlers can I control? =

GPTBot, OAI-SearchBot, ChatGPT-User, ClaudeBot, Claude-Web, anthropic-ai, PerplexityBot, Perplexity-User, Google-Extended, Applebot-Extended, CCBot, Bytespider, meta-externalagent, Amazonbot and cohere-ai. Each can be allowed, blocked or left at default in robots.txt.

= Does it slow my site down? =

No. Head tags are built from post meta at render time, the sitemap and LLM files are generated only when requested, and the dashboard analysis is cached per post and refreshed on save.

= Where is the data stored? =

Settings are a single option; per-page values are post meta prefixed with _daeo_. On uninstall the plugin removes its option, and also the post meta if you opted in on the Settings page.

= Is there a Pro version? =

Yes. AI SEO Pro is a separate add-on plugin ($19.95 or $39.95 per month, differing only in included AI usage) that generates meta titles and descriptions, FAQ items, complete draft pages and per-page advice using AI. See https://demfilz.com/ai-seo. The free plugin is complete on its own and never locks features.

== Screenshots ==

1. The AI SEO meta box with snippet preview.
2. The FAQ manager on a page.
3. The Suggestions dashboard with scores.
4. Settings: modules and site identity.
5. AI crawler controls.
6. llms.txt served on the front end.

== Changelog ==

= 1.0.0 =
* Initial release: meta titles/descriptions with snippet preview, canonical and noindex, Open Graph and Twitter cards, FAQ manager with FAQPage schema, Organization/WebSite/Article/BreadcrumbList schema, XML sitemap, llms.txt, llms-full.txt, markdown page mirrors, AI crawler robots controls, breadcrumbs shortcode and the optimization suggestions dashboard.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

=== SearchMiner ===
Contributors: yodzira
Tags: search, woocommerce, analytics, zero results, insights
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See what visitors search on your site and what your search can't find — without replacing your search engine.

== Description ==

SearchMiner is a passive observer for your site search. It records what
visitors search for, flags every search that returned zero results and shows
searches where visitors got results but clicked nothing — the quiet revenue
leak most sites never measure.

**It does not replace your search.** Keep the search you already have (native
WordPress search, WooCommerce product search) — SearchMiner simply tells you
what it is missing.

Highlights:

* Top searches, top zero-result searches and top "searched but clicked nothing" queries
* Searches per day chart with the zero-result share highlighted
* WooCommerce aware: product searches are marked separately
* Dashboard widget: the zero-result searches your visitors made most
* Privacy first: no IP addresses, no user accounts stored; automatic retention
  (30–365 days); one-click erase of all recorded searches
* Bots and crawlers are filtered out, so numbers mean people
* No data leaves your server

= How it works =

When a visitor loads a search results page, SearchMiner records the phrase
and the number of results. If result tracking is enabled, a tiny script on
the results page sends one beacon when the visitor clicks a result — or one
beacon when they leave without clicking anything. That is all.

= Limitations (honest list) =

* Searches served entirely from a full-page cache bypass PHP and are not recorded
* AJAX search widgets (third-party) are not recorded in this version
* The plugin counts behaviour; it cannot tell you visitor intent

== Pro Version ==

Pro adds automation, reports and integrations on top of the free version
(one license = one site, 12 months of updates):

https://yodsira.duckdns.org/buy/searchminer

== Installation ==

1. Install via Plugins → Add New → search for "SearchMiner", or upload the zip.
2. Activate. That is it — capture starts immediately.
3. Open SearchMiner in the admin menu after some searches have happened.

== Frequently Asked Questions ==

= Does it replace my search? =

No. SearchMiner observes any search based on the standard WordPress search
query, including WooCommerce product search.

= Do you store IP addresses or personal data? =

No IP addresses are stored. The plugin records search phrases, counts and
timestamps only. Retention is configurable (30–365 days) and everything can
be erased with one click.

= Will it slow my site down? =

Capture is a single indexed upsert that runs only on search pages. Nothing
runs on regular page views.

= Does it work with Relevanssi / Ivory Search / FiboSearch? =

If those plugins use the standard WordPress search query, SearchMiner sees
it. Purely AJAX third-party search widgets are not recorded in this version.

== Changelog ==

= 0.1.0 =
* Initial release: capture, dashboard, settings, privacy tools, click tracking.

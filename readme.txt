=== Voice ADD for WooCommerce ===
Contributors: portallcomua
Tags: woocommerce, voice, products, csv, microphone
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 4.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create WooCommerce products by voice, photos, attributes, variations and CSV import/export.

== Description ==

Voice ADD for WooCommerce helps store managers create WooCommerce products without filling every field manually.

Features:
* Create products by speaking product details into the browser microphone.
* Use voice field markers for product name, price, sale price, SKU, stock, weight, categories, tags and description.
* Add product photos from a camera or device gallery.
* Create product attributes and variable products with variations.
* Import products from CSV and export product data to CSV.
* Choose whether new products are saved as drafts or published.
* Ukrainian and English interface.
* Assign access to selected WordPress user roles.

The free version does not impose a product creation quota or disable features after a trial.

WooCommerce must be installed and active.

An optional commercial PRO edition is available with additional features. The free plugin remains usable without purchasing PRO:
https://uaserver.pp.ua/voice-add-woocommerce-pro/

== Installation ==

1. Install and activate WooCommerce.
2. Upload the `voice-add-woocommerce` folder to the `/wp-content/plugins/` directory, or install the ZIP through Plugins > Add New Plugin > Upload Plugin.
3. Activate **Voice ADD for WooCommerce**.
4. Open **Voice ADD WC** in the WordPress dashboard.
5. Allow microphone and camera access when your browser asks.
6. Select the product fields, dictate the values, add photos if needed, and save the product.
7. Open **Voice ADD WC > Settings** to choose the interface language, product status and permitted user roles.

For voice recognition, use a browser that supports the Web Speech API (for example, a current version of Google Chrome) and serve the website over HTTPS.

== Frequently Asked Questions ==

= Does it work without WooCommerce? =

No. WooCommerce must be installed and active.

= Is there a product limit in the free version? =

No. The WordPress.org version does not impose a product creation quota.

= Does the plugin send product data to UAServer? =

No. The plugin does not send product data, store details, usage statistics or personal data to UAServer.

= Does voice recognition send audio to the plugin developer? =

No. Voice recognition is provided by the browser and its configured speech recognition provider. Audio processing and privacy practices depend on the browser/provider. The plugin itself does not upload microphone recordings to UAServer.

= Can I import images from URLs in a CSV file? =

Yes. When you import a CSV containing image URLs, your WordPress server downloads those images from the URLs you supplied and adds them to the Media Library. Each CSV upload can be up to 5 MB.

= How can I contact support? =

Email: support@uaserver.pp.ua

== External services ==

The plugin makes no background requests to developer, licensing or payment servers. The only external destinations are used after an explicit user action: browser speech recognition, image URLs supplied in a CSV import, and the optional PRO information link.

Browser speech recognition:
* What it is: The browser's built-in Web Speech API or equivalent speech recognition implementation.
* When it runs: Only after a user presses the Dictate button and grants microphone permission.
* Data sent: Audio may be processed by the browser vendor or its configured speech recognition provider. In Google Chrome, speech recognition may use Google's service. The plugin receives the resulting transcript, not the audio recording.
* Provider policies: Google Privacy Policy: https://policies.google.com/privacy. Web Speech API information: https://developer.mozilla.org/en-US/docs/Web/API/Web_Speech_API. Other browsers may use different providers and policies.

Remote product images:
* What it is: Images hosted at URLs supplied by the store administrator in a CSV import file.
* When it runs: Only when an administrator or permitted store manager imports a CSV containing image URLs.
* Data sent: The WordPress server requests the specified image URL to download the image. The plugin does not send store credentials or customer data with this request.

UAServer PRO information page:
* What it is: An optional product information page at https://uaserver.pp.ua/voice-add-woocommerce-pro/.
* When it runs: Only when a user clicks the "Learn about PRO" link in the plugin's own administration page.
* Data sent: The user's browser opens the page and sends the standard web request metadata to uaserver.pp.ua. The plugin does not send product data, site content or account credentials.

== Privacy ==

The plugin stores its settings in the WordPress options table and uses WordPress user roles/capabilities to control access. Product data and images are saved to the site's own WordPress/WooCommerce database and Media Library.

== Changelog ==

= 4.0.0 =
* Prepared the free edition for the WordPress.org Plugin Directory.
* Removed external license checks, payment integrations and GitHub-based plugin updates.
* Removed the product quota: product creation and CSV import are not paywalled.
* Removed the optional remove.bg integration pending the provider's announced service migration.
* Added a non-intrusive link to the optional PRO edition.
* Improved role setup and image URL validation.
* Added WordPress.org readme and external-service disclosures.

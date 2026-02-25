=== Bootflow – Product XML & CSV Importer ===
Contributors: bootflow
Tags: woocommerce, import, xml, csv, products
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.9.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bootflow Product XML & CSV Importer – import and update WooCommerce products from XML and CSV feeds using a reliable manual mapping workflow.

== Description ==

**Bootflow – Product XML & CSV Importer** is a professional product import tool for WooCommerce by Bootflow.

It allows you to import and update products from XML or CSV files using a clear and deterministic mapping interface.  
The free version is fully usable for real WooCommerce stores and does not impose artificial product limits.

The Pro version adds automation, selective updates, and optional AI-assisted data processing.

= Key Features =

* Import products from XML and CSV files
* Manual field mapping with full control
* Support for simple and variable products
* Attributes and variations handling
* Update existing products matched by SKU
* Skip unchanged products to reduce import time
* Unlimited number of products
* Local file uploads

== Free Version ==

The free version provides a complete manual import workflow.

FREE features include:

* Unlimited products (no product count limits)
* XML and CSV file import
* Manual field mapping
* Simple and variable products
* Attributes and variations
* Manual imports
* Update existing products (all fields)
* Skip unchanged products
* Local file uploads

The free version does **not** use AI and does **not** send any data to external services.

== Pro Version ==

The Pro version focuses on automation, control, and saving time when working with large or frequently updated product feeds.

PRO features include:

* Import from remote XML and CSV URLs
* Automatic field mapping suggestions
* Scheduled imports using WP-Cron
* Selective field updates (e.g. price and stock only)
* Advanced update rules and conditions
* Reusable import templates
* Detailed import logs and error reporting
* AI-assisted field mapping and data transformation
* AI-powered translation of product content
* Custom AI prompts per field
* Priority support

AI features are optional and require user-provided API keys.  
No data is sent to external services unless explicitly enabled by the user.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/woocommerce-xml-csv-importer/` directory, or install the plugin through the WordPress Plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to WooCommerce → XML & CSV Importer to start importing products.

== Frequently Asked Questions ==

= What file formats are supported? =
XML and CSV files are supported in both the free and Pro versions.

= Is there a product limit in the free version? =
No. The free version does not limit the number of products.

= Can I update existing products? =
Yes. Products can be matched by SKU.
The free version updates all fields.
The Pro version allows selecting specific fields (such as price or stock).

= Does the free version use AI? =
No. AI features are available only in the Pro version and are optional.

= Is my data sent to external servers? =
No data is sent in the free version.
In the Pro version, data is sent to AI services only if the user enables AI features and provides API keys.

= Are variable products supported? =
Yes. Variable products with attributes and variations are fully supported.

== Requirements ==

= WooCommerce Compatibility =
* WooCommerce 6.0 or higher required
* Tested up to WooCommerce 9.0
* Compatible with WooCommerce HPOS (High-Performance Order Storage)

== Screenshots ==

1. Import wizard – file upload
2. Field mapping interface
3. Import behavior configuration
4. Import progress and results
5. Variable products and attributes

== Changelog ==

= 0.9.1 =
* WordPress.org compliance fixes
* Replaced curl with WordPress HTTP API
* Fixed text domain consistency

= 0.9.0 =
* Initial public release
* XML and CSV import support
* Manual field mapping
* Simple and variable products
* Attributes and variations
* Update existing products
* Skip unchanged products

== Upgrade Notice ==

= 0.9.0 =
Initial public release.


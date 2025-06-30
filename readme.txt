=== One Stop Shop for WooCommerce ===
Contributors: vendidero, vdwoocommercesupport
Tags: one stop shop, woocommerce, OSS, EU, compliance
Requires at least: 5.4
Tested up to: 6.8
WC requires at least: 3.9
WC tested up to: 9.9
Stable tag: 1.8.0
Requires PHP: 5.6
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

The One Stop Shop compliance helper allows you to easily monitor your One Stop Shop delivery threshold within WooCommerce and generate detailed tax reports.

== Description ==

One Stop Shop for WooCommerce adds compliance with the new [One Stop Shop procedure](https://vat-one-stop-shop.ec.europa.eu) of the european union to WooCommerce.
With the help of this little plugin you may easily monitor the delivery threshold (10.000 €) for B2C exports to other EU countries. Furthermore you may generate tax reports (e.g. quarterly, monthly, yearly) applicable to the OSS procedure and export them as CSV to easily notify your local tax authorities about your sales.

* *Delivery Threshold Observation* - The plugin may automatically observe the yearly delivery threshold and send notices by email and via the admin panel in case the threshold is close to being reached.
* *Detailed Tax Reports* - Generate detailed tax reports (e.g. per tax rate, per country) of your WooCommerce orders and export them as CSV.
* *Refresh Tax Rates* - Automatically refresh your EU tax rates based on scheduled changes, e.g. (new/updated) tax rates for Estonia starting 01-01-2024.
* *Import Tax Rates* - Import tax rates based on current EU VAT rates.
* *Tax Classes per Country* - Depending on the product, different tax classes may apply for different EU countries. Choose tax classes per country for your WooCommerce products.

== Installation ==

= Minimal Requirements =

* WordPress 4.9 or newer
* WooCommerce 3.9 (newest version recommended)
* PHP Version 5.6 or newer

= Automatic Installation =

We recommend installing One Stop Shop for WooCommerce through the WordPress Backend. Please install WooCommerce before installing our plugin.
After the installation you may easily adapt the settings to your own needs.

== Frequently Asked Questions ==

= Where can I find the documentation? =
[One Stop Shop Documentation](https://vendidero.github.io/one-stop-shop-woocommerce/)

= Need help? =

You may ask your questions regarding One Stop Shop for WooCommerce within our free [WordPress Support Forum](https://wordpress.org/support/plugin/one-stop-shop-woocommerce).

= The reports never finish =

Reports are created with the help of the [WooCommerce Action Scheduler](https://actionscheduler.org/). Batch requests are used to make sure that your server may incrementally generate reports for all applicable orders.
You will need to make sure the [WP Cron](https://developer.wordpress.org/plugins/cron/) (which is being used by the WooCommerce Action Scheduler) works within your setup.

= Want to file a bug or improve the plugin? =

Bug reports may be filed via our [GitHub repository](https://github.com/vendidero/one-stop-shop-woocommerce).

== Screenshots ==

1. General settings screen
2. Reports UI
3. Create a new report

== Changelog ==
= 1.8.0 =
* Improvement: Timezone handling

= 1.7.1 =
* Improvement: Bump BOP CSV export version to 2.0, see https://www.elster.de/bportal/helpGlobal?themaGlobal=osseust_import

= 1.7.0 =
* Improvement: Work with UTC dates to build order queues

= 1.6.4 =
* Improvement: Updated FI tax rates

= 1.6.3 =
* New: Indicate WP latest + Woo latest support
* Improvement: Local pickup detection

= 1.6.2 =
* Fix: Revert (default) exclusion of (potential) b2b orders without vat id as it is highly controversial
* Improvement: Added woocommerce_eu_tax_helper_exclude_b2b_without_vat_id_from_oss filter to allow b2b exclusion

= 1.6.1 =
* Fix: Apply base taxes for EU-wide b2b orders (without UID/vat exempt) only

= 1.6.0 =
* Improvement: Exclude b2b orders (without and with UID, e.g. non-taxes) from OSS
* Improvement: Calculate base location taxes for b2b checkouts

= 1.5.1 =
* Improvement: Refresh luxembourg tax rate

= 1.5.0 =
* New: Automatically refresh tax rates once per day based on changesets

= 1.4.0 =
* New: Added Woo HPOS report compatibility
* Improvement: Exclude local pickup orders

= 1.3.9 =
* Fix: Tax rate type detection (standard vs. reduced)
* Fix: Tax class formatting during import

= 1.3.8 =
* Improvement: Prevent tax rate cache under certain circumstances

= 1.3.7 =
* Fix: Do only adjust country-specific product tax class for EU VAT countries

= 1.3.6 =
* Fix: Include recipient field in admin threshold notification email settings

= 1.3.5 =
* Improvement: Updated LU tax rates
* Improvement: Tax rate filtering while recalculating order totals

= 1.3.4 =
* Fix: Update textdomain to reflect plugin slug
* Fix: Fallback language file path

= 1.3.3 =
* Improvement: Switch plugin locale to de_DE when using de_CH or de_AT

= 1.3.2 =
* Fix: Jetpack autoloader version constraint in composer.json

= 1.3.1 =
* Fix: Explicitly exclude base country in reports

= 1.3.0 =
* Improvement: Refactored EU VAT checks to shared library
* Fix: Use EL instead of GR for BOP exports

= 1.2.4 =
* Improvement: PHP Code sniffer refactoring

= 1.2.3 =
* Improvement: Harden URL escaping

= 1.2.2 =
* Improvement: Fallback to standard tax class for countries which are missing the reduced tax class.

= 1.2.1 =
* Feature: Added BOP (BZSt-Online-Portal) export for Germany

= 1.2.0 =
* Improvement: Do not skip refunded parent orders

= 1.1.9 =
* Improvement: BOP CSV export (new format with Satzart)

= 1.1.8 =
* Fix: Decimal tax rates within reports

= 1.1.7 =
* Fix: Tax rate import for disabled OSS status

= 1.1.6 =
* Improvement: Allow choosing relevant date type for reports
* Improvement: Experimental BOP compatible CSV Exporter for Germany via oss_experimental_use_de_bop_csv_exporter filter
* Improvement: Shipping package invalidation check
* Fix: Date paid range

= 1.1.5 =
* Fix: Prevent calling missing refund order getters

= 1.1.4 =
* Improvement: Query optimization

= 1.1.3 =
* Improvement: Explicitly check whether WC_Tax::get_rate_percent_value() is available

= 1.1.2 =
* Improvement: Report cleanup
* Improvement: OSS VAT exempts (calculate net price based on base address)

= 1.1.1 =
* Improvement: Report deletion and queue cancelling
* Improvement: Added wpml-config.xml to support copying meta data for translated products

= 1.1.0 =
* Feature: Allow disabling fixed gross prices
* Improvement: Use custom SQL query instead of wc_get_orders
* Improvement: Treat refunds as separated orders and respect their dates
* Improvement: Feature plugin load management
* Improvement: Added postcode VAT exemptions as tax rates (e.g. canary islands)
* Improvement: Added conditional rates for Portugal (e.g. Madeira)
* Improvement: By default do not force gross prices for third countries
* Improvement: Backdate the observer to 7 days in the past to allow orders to complete in between

= 1.0.5 =
* Improvement: Remove docs from release
* Improvement: Filters for tax rate name and location price

= 1.0.4 =
* Improvement: Woo 5.5 support
* Improvement: Tax rate import
* Improvement: Admin order tax (re) calculation
* Fix: Admin note removal

= 1.0.3 =
* Improvement: Support Norther Ireland via postcode
* Improvement: Action Scheduler search args
* Improvement: Do only remove EU VAT rates during import
* Improvement: Added status tool to (re-) import EU VAT rates

= 1.0.2 =
* Improvement: Reduce query batch size
* Fix: NOT EXISTS taxable country query

= 1.0.1 =
* Improvement: Setting URL
* Improvement: Tax class name detection
* Fix: Germanized integration detection

= 1.0.0 =
* Initial commit

== Upgrade Notice ==

= 1.0.0 =
no upgrade - just install :)
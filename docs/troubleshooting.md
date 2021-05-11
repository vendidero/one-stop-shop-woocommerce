---
title: Troubleshooting
has_children: false
nav_order: 4
---

# Troubleshooting

In case you are facing issues with the plugin, please use our [support forum](https://wordpress.org/support/plugin/one-stop-shop-woocommerce/) to submit your question.

Some typical issues will be explained here.

### Reports never complete

This issue might be related to problems with WP Cron and the WooCommerce Action Scheduler. Most of the times some security-related issues exist (e.g. htaccess configuration).
You can check pending actions used by OSS under WooCommerce > Status > Actions by searching for `oss_woocommerce`.

### Slow loading times

Depending on your server performance and order count, generating reports is a resource-intensive as the reports are calculated asynchronously by the WooCommerce Action Scheduler.
One way to overcome this issue is by placing the following snippet within your active themes' functions.php:

```php
<?php
add_filter( 'oss_woocommerce_report_batch_size', function( $batch_size ) { return 20; } );
```

This snippet will reduce the maximum amount of orders processed within one iteration to 20.
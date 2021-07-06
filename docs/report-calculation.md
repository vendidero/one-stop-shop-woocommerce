---
title: Calculation
parent: Reports
has_children: false
nav_order: 1
permalink: /report-calculation
---

# Report calculation

Tax reports include orders applicable for the OSS procedure, e.g.:

- Inner EU shipment (except base country)
- Tax total greater than 0

The report will then calculate net total and tax total per country and tax rate for the order applicable.

Depending on your order count the report is quite processing-heavy - that's why the OSS plugin uses the [WooCommerce Action Scheduler](https://actionscheduler.org/) to split the calculation into multiple iterations.

Filters exist to adjust certain report-specific details, e.g. whether the order paid date should be used to query the orders.
Please be aware that adjusting these filters will only lead to adjustments for future reports.

### Observer report

The observer report is a special report type which constantly observes whether you are approaching the delivery threshold for the current year.
The observer lags 7 days behind to make sure that your customers had enough time to pay for open orders as by default only paid 
orders are part of a report.

### Filters

- `oss_woocommerce_report_batch_size` - The maximum amount of orders processed per iteration. Default: `50`
- `oss_woocommerce_report_use_date_paid` - Whether to use the date paid of the order for queries. Default: `true`
- `oss_woocommerce_report_include_order` - Whether to include/exclude a specific order while processing.
- `oss_woocommerce_valid_order_statuses` - List of valid order statuses. Defaults to all order statuses except `wc-refunded, wc-pending, wc-cancelled, wc-failed`
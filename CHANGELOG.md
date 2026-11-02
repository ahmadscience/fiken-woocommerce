# Changelog

## 1.17-MB-4 (2026-11-02)

Security and bookkeeping release. Contains the changes from pull requests #2 and #3.

### Before you update

- **PHP 7.4 or newer** is required (the new PDF library needs it). WordPress will not activate
  the plugin on older PHP.
- **Map every taxed WooCommerce tax rate** under *Fiken > Settings > VAT Mapping*. An order with
  VAT on an unmapped rate is now marked *Failed* with a message naming the rate, instead of
  being sent to Fiken without VAT. Map the rate and transfer the order again.
- The **password field is empty** when the settings page loads. Leave it empty to keep the saved
  password; type a new one only to change it.
- TLS certificate checking is now on for Fiken calls. If transfers start failing with a
  certificate error, the server's CA certificates are out of date.

### After you update, check on staging

1. Open *WooCommerce > Fiken*, save the settings, transfer one order.
2. In Fiken, confirm the sale exists once and has the PDF invoice attached.

### Security

- Only shop managers (`manage_woocommerce`) with a valid nonce can use the plugin's AJAX
  actions. Before, any logged-in user, including shop customers, could transfer orders, read
  transfer history and **overwrite the Fiken login and password**.
- Settings can only be saved from the settings form, with a nonce (fixes CSRF).
- SQL injection through the order list's `sort` / `asc_desc` parameters fixed; filter dates,
  status and page number are validated (also fixes a reflected XSS).
- TLS certificate verification turned on for all Fiken API calls (was off).
- All output on the admin page, in its JavaScript and in the PDF invoice is escaped.
- The stored Fiken password is no longer sent to the browser.
- PDF library dompdf upgraded from 0.8.3 (end-of-life, known CVEs) to 3.1.6, with remote
  files, PHP and JavaScript disabled.

### Bookkeeping fixes

- **No duplicate sales.** An order already in Fiken is not sent again when its status changes
  again or in a bulk transfer. A single order can still be re-sent on purpose after confirming.
- Two requests at the same time (payment callback and customer redirect, double click) can no
  longer send the same order twice (per-order database lock).
- Order lines under 1 kr (e.g. a 0.50 kr fee) are no longer dropped.
- Taxed lines on an unmapped tax rate stop with a clear error instead of being booked without VAT.
- The company *name* is no longer sent as organisation number; only a 9-digit number is.
- An order without an email no longer gets linked to an unrelated Fiken contact.
- The status-change hook no longer resets a *Failed* order to *Not transferred*.

### Other fixes

- **PDF invoices are attached again on PHP 8.1+** (dompdf 0.8.3 crashed there, so no invoice
  was attached).
- Works on PHP 8.4 without deprecation warnings.
- Order list: the end date filters on order date (was modified date).
- Transferring an unknown order no longer causes a fatal error.
- Uses WooCommerce 3.0+ order APIs instead of deprecated properties.
- Fixed error messages and translations; jQuery UI stylesheet loads over https; fixed a broken
  `selected` attribute.

### New

- Admin notice for shop managers when orders failed to transfer in the last 30 days, linking to
  the failed orders.
- WooCommerce is told the plugin does not support HPOS (High-Performance Order Storage), so it
  warns before HPOS is enabled instead of the Fiken order list silently going empty.
- Automated tests (`php tests/run.php`) and GitHub Actions CI on PHP 7.4 to 8.4.
- README with requirements, behaviour and development notes.

### Known limitations

- Uses Fiken API v1 with username and password. Fiken's current documentation only describes
  API v2; if v1 stops working, the plugin needs to be moved to v2.
- Transfers run during the order status change (not in a background job), so a slow Fiken API
  slows down that request.
- No HPOS support.

## 1.17-MB-3

Changes on top of Fiken's 1.17:

- PDF invoice generation enabled; the invoice is attached to the sale in Fiken.
- Use order number, not order ID, as invoice number.
- PDF uses DejaVu Sans instead of downloading Open Sans.
- Medium-VAT (15 %) lines are booked on income account 3040.
- Shipping VAT type calculated from the shipping tax rate instead of a fixed mapping.
- Rounding deferred to the end of a sale and aligned with WooCommerce.
- Logging improvements.

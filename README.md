# Fiken for WooCommerce (modified)

Sends paid WooCommerce orders to [Fiken](https://fiken.no) as sales, with a PDF invoice attached.

See [CHANGELOG.md](CHANGELOG.md) for what changed in each version and what to check when updating.

## Requirements

- WordPress with WooCommerce 3.0 or newer
- PHP 7.4 or newer
- Orders stored in `wp_posts`: the plugin does **not** support WooCommerce's High-Performance
  Order Storage (HPOS) and declares that, so WooCommerce warns before HPOS is switched on.
- Uses Fiken API v1 (`https://fiken.no/api/v1`) with the Fiken login and password.

## How transfers work

- When an order changes to the status configured for its payment method (Fiken > Settings),
  it is sent to Fiken. An order that is already in Fiken is never sent again automatically;
  on the Fiken page it can be re-sent after a confirmation.
- A WooCommerce tax rate with VAT that is not mapped to a Fiken VAT type stops the transfer
  with an error naming the rate, instead of being booked without VAT.
- Failed transfers in the last 30 days are shown as a notice in wp-admin.

## Development

Run the tests (no WordPress or network needed; WordPress is stubbed):

```bash
php tests/run.php
```

CI runs them on PHP 7.4 to 8.4 for every pull request (`.github/workflows/ci.yml`).

The PDF library (dompdf) is committed under `pdf/lib/vendor`. To update it:

```bash
cd pdf/lib && composer update --no-dev
```

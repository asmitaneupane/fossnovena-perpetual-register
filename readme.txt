=== Fossnovena Perpetual Register ===
Contributors: Asmita Neupane
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 1.0.0
License: GPLv2 or later

A CSV-powered Perpetual Register with Replace/Append import, shortcode display, search & pagination.

== Shortcode ==
[perpetual_register per_page="100" sort="asc" search="true" show_download="false"]

== Admin ==
Tools → Fossnovena: upload CSV, choose "Replace" or "Append".

== Storage ==
- Custom table: wp_fn_perpetual_register (id, full_name, created_at, UNIQUE full_name)
- Master CSV: /wp-content/uploads/fossnovena/perpetual-register.csv

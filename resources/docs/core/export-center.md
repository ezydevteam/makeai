---
title: Exporting Data From the Export Center
slug: export-center
page: export-center.html
section: Export Center
license: regular
keywords: [export, export center, csv, xlsx, pdf, scheduled export, report, data export, download data]
---

**Admin → Export Center** exports data out of your site — customers, transactions, AI usage, and more — as a file you can download, without writing a query.

## Choosing what to export

Pick a **dataset** from the list (each one is a distinct data type, like Users or Transactions), then choose which columns to include — column choices are specific to the dataset you picked and reset when you switch datasets. Choose a **format**: XLSX, CSV, or PDF (PDF exports are limited compared to the spreadsheet formats, since it's built for reading rather than re-importing).

## Saving a preset for repeat exports

If you run the same export regularly, save your current dataset, format, filters, and column selection as a named **preset** — applying a preset instantly restores all of those choices so you don't have to rebuild them each time.

## Scheduling a recurring export

Beyond one-off downloads, set up a named **scheduled export** with its own dataset, format, and frequency (Daily, Weekly, or Monthly) — it runs automatically going forward. Toggle a schedule on or off, or delete it, from the same screen.

## Why an export is missing data or columns

- A column you expect isn't in the list — column options are tied to the selected dataset, and switching datasets resets which columns are available and selected.
- **PDF** exports show less than XLSX/CSV — this is expected, since PDF format has stricter limits than the spreadsheet formats.
- A scheduled export produced nothing — check it's still toggled on; toggling it off pauses future runs without deleting the schedule itself.

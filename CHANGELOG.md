# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## [0.2.2] - 2026-11-18
### Changed
- PhpSpreadsheet 5 and Laravel 13.
- Numeric cells, including negative and currency-formatted values, keep their numeric form.
- Text a spreadsheet would read as a formula comes back as literal text by default; pass `neutralize_formulas=false` for the raw cell text.
- Stability and reliability improvements.

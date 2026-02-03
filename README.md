# WooCommerce XML & CSV Importer

A professional WooCommerce product importer for XML and CSV feeds.

- **WordPress:** 5.8+
- **PHP:** 7.4+
- **License:** GPL v2 or later
- **Landing page:** https://bootflow.io/

## Overview

**WooCommerce XML & CSV Importer** lets you import and update WooCommerce products from XML and CSV files using a reliable manual mapping workflow.

The **Free** version is fully usable for real WooCommerce stores (no product limits).  
The **Pro** version adds automation, selective updates, and optional AI-assisted data processing.

---

## Free vs Pro

### Free (WordPress.org)
- Import **XML & CSV** files (local upload)
- **Manual field mapping**
- Supports **simple & variable** products
- Attributes and variations support
- Update existing products by SKU (**updates all fields**)
- Skip unchanged products
- **Unlimited products**
- No AI features and **no external data transfer**

### Pro (bootflow.io)
- Import from **remote feed URLs**
- **Scheduled imports** (WP-Cron / server cron)
- **Selective field updates** (e.g. update only **price** and **stock**)
- Automatic mapping suggestions
- Advanced rules/conditions
- Import templates
- Detailed logs and error reporting
- **Pricing Engine PRO** - conditional pricing rules
- Optional **AI-assisted mapping, transformation and translation**
  - Requires user-provided API keys
  - No data is sent unless explicitly enabled by the user

---

## Pricing Engine

The **Pricing Engine** allows automatic price calculation during import:

### How it works
1. Select the **base price field** from your XML/CSV
2. Define **pricing rules** with conditions
3. Each rule can have: markup %, fixed amount, rounding, min/max limits
4. Rules are evaluated **top to bottom** - first matching rule applies
5. **Default Rule** is the fallback if no conditional rules match

### Rule Conditions (PRO)
- **Price Range**: Apply different markup for different price tiers
- **Category**: Different markup per product category
- **Brand**: Brand-specific pricing
- **Supplier**: Supplier-based markup
- **XML Field**: Any XML field with operators (=, ≠, contains, >, <, etc.)
- **SKU Pattern**: Match SKU prefixes, suffixes or patterns

### Condition Logic
- **ALL conditions match** (AND) - all conditions must be true
- **ANY condition matches** (OR) - at least one condition must be true

### ⚠️ Avoiding Conflicts
Rules are evaluated **top to bottom**. To avoid unexpected behavior:
- Place more specific rules **above** general rules
- Avoid overlapping conditions between rules
- Use the **Live Preview** to test each rule
- The **Default Rule** always applies as fallback

---

## How it works (high-level)

1. Upload an XML/CSV feed (Free) or connect a URL feed (Pro)
2. Map source fields to WooCommerce fields
3. Configure update behavior (Free: all fields; Pro: selected fields)
4. Run the import (manual or scheduled in Pro)

---

## Documentation

This README is intentionally concise.  
For detailed field lists, processing modes, cron examples and developer notes, use project documentation:

- Landing page: https://bootflow.io/

---

## Support

- Free: community support (WordPress.org)
- Pro: priority support (bootflow.io)

---

## Changelog

See `readme.txt` for the WordPress.org changelog.


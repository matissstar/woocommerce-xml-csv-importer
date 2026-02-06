#!/bin/bash
#
# Build script for BootFlow WooCommerce XML/CSV Importer
# Creates FREE and PRO distribution ZIP files
#
# Usage: ./build.sh
#

set -e

# Configuration
VERSION="0.9.2-build-$(date +%Y%m%d-%H%M)"
PLUGIN_SLUG="bootflow-woocommerce-xml-csv-importer"
PRO_SLUG="${PLUGIN_SLUG}-pro"

# Directories
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SRC_DIR="$SCRIPT_DIR"
DIST_DIR="$SCRIPT_DIR/dist"
BUILD_DIR="$SCRIPT_DIR/build-temp"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Building WooCommerce XML/CSV Importer${NC}"
echo -e "${GREEN}Version: ${VERSION}${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# Clean up previous builds
echo -e "${YELLOW}Cleaning previous builds...${NC}"
rm -rf "$BUILD_DIR"
rm -rf "$DIST_DIR"
mkdir -p "$BUILD_DIR"
mkdir -p "$DIST_DIR"

# ========================================
# BUILD FREE VERSION
# ========================================
echo ""
echo -e "${GREEN}[1/4] Building FREE version...${NC}"

FREE_BUILD_DIR="$BUILD_DIR/$PLUGIN_SLUG"
mkdir -p "$FREE_BUILD_DIR"

# Copy all files except those in exclude list
rsync -av \
    --exclude-from="$SCRIPT_DIR/build-config/free-exclude.txt" \
    --exclude="dist/" \
    --exclude="build-temp/" \
    "$SRC_DIR/" "$FREE_BUILD_DIR/"

# Modify main plugin file for FREE version
echo -e "${YELLOW}  Modifying plugin for FREE edition...${NC}"

# Change IS_PRO constant to false
sed -i "s/define('WC_XML_CSV_AI_IMPORT_IS_PRO', true);/define('WC_XML_CSV_AI_IMPORT_IS_PRO', false);/g" \
    "$FREE_BUILD_DIR/woocommerce-xml-csv-importer.php"

# Update plugin header for FREE - no change needed since source already has correct Bootflow branding
# Just ensure the name stays as-is (Bootflow – WooCommerce XML & CSV Importer)

# Remove AI-related require statements from main file (they'll cause errors without the files)
sed -i '/class-wc-xml-csv-ai-import-ai-providers\.php/d' "$FREE_BUILD_DIR/woocommerce-xml-csv-importer.php"
sed -i '/class-wc-xml-csv-ai-import-scheduler\.php/d' "$FREE_BUILD_DIR/woocommerce-xml-csv-importer.php"

# Also remove from class-wc-xml-csv-ai-import.php if it loads these
if [ -f "$FREE_BUILD_DIR/includes/class-wc-xml-csv-ai-import.php" ]; then
    sed -i '/class-wc-xml-csv-ai-import-ai-providers\.php/d' "$FREE_BUILD_DIR/includes/class-wc-xml-csv-ai-import.php"
    sed -i '/class-wc-xml-csv-ai-import-scheduler\.php/d' "$FREE_BUILD_DIR/includes/class-wc-xml-csv-ai-import.php"
fi

# Remove eval() and create_function usage (WordPress.org requirement)
echo -e "${YELLOW}  Removing eval() and PRO features for WordPress.org compliance...${NC}"

# In admin.js, comment out or remove any eval usage
if [ -f "$FREE_BUILD_DIR/assets/js/admin.js" ]; then
    # Remove PHP processing mode references
    sed -i 's/processing_mode.*php/processing_mode: "direct"/g' "$FREE_BUILD_DIR/assets/js/admin.js"
fi

# Remove eval() from PHP files - replace with safe stubs
# In processor.php - replace safe_eval function to return original value
if [ -f "$FREE_BUILD_DIR/includes/class-wc-xml-csv-ai-import-processor.php" ]; then
    # Comment out the eval line and return original value
    sed -i 's/\$result = eval.*return.*eval_code.*;/\$result = \$value_param; \/\/ eval disabled in FREE version/g' "$FREE_BUILD_DIR/includes/class-wc-xml-csv-ai-import-processor.php"
    # Remove AI_Providers instantiation
    sed -i 's/\$this->ai_providers = new WC_XML_CSV_AI_Import_AI_Providers();/\$this->ai_providers = null; \/\/ AI disabled in FREE version/g' "$FREE_BUILD_DIR/includes/class-wc-xml-csv-ai-import-processor.php"
fi

# In importer.php - replace eval with direct value return
if [ -f "$FREE_BUILD_DIR/includes/class-wc-xml-csv-ai-import-importer.php" ]; then
    sed -i 's/\$result = @eval(\$wrapped_formula);/\$result = \$base_price; \/\/ eval disabled in FREE version/g' "$FREE_BUILD_DIR/includes/class-wc-xml-csv-ai-import-importer.php"
fi

# In admin.php - replace eval calls with safe returns
if [ -f "$FREE_BUILD_DIR/includes/admin/class-wc-xml-csv-ai-import-admin.php" ]; then
    sed -i 's/\$result = eval(\$wrapped_formula);/\$result = \$test_value; \/\/ eval disabled in FREE version/g' "$FREE_BUILD_DIR/includes/admin/class-wc-xml-csv-ai-import-admin.php"
    # Remove AI_Providers instantiation
    sed -i 's/\$ai_providers = new WC_XML_CSV_AI_Import_AI_Providers();/\$ai_providers = null; \/\/ AI disabled in FREE version/g' "$FREE_BUILD_DIR/includes/admin/class-wc-xml-csv-ai-import-admin.php"
fi

# Remove AI Providers tab and Scheduler references from settings-page.php
if [ -f "$FREE_BUILD_DIR/includes/admin/partials/settings-page.php" ]; then
    # Remove AI Providers tab link
    sed -i '/<a href="#ai-providers".*AI Providers/d' "$FREE_BUILD_DIR/includes/admin/partials/settings-page.php"
    # Comment out Scheduler::is_action_scheduler_available calls
    sed -i 's/WC_XML_CSV_AI_Import_Scheduler::is_action_scheduler_available()/true \/\/ Scheduler disabled in FREE/g' "$FREE_BUILD_DIR/includes/admin/partials/settings-page.php"
fi

# Remove AI/Hybrid/PHP_formula options from import-edit.php
if [ -f "$FREE_BUILD_DIR/includes/admin/partials/import-edit.php" ]; then
    # Remove hybrid and ai_processing options from dropdown
    sed -i '/<option value="hybrid"/d' "$FREE_BUILD_DIR/includes/admin/partials/import-edit.php"
    sed -i '/<option value="ai_processing"/d' "$FREE_BUILD_DIR/includes/admin/partials/import-edit.php"
    sed -i '/<option value="php_formula"/d' "$FREE_BUILD_DIR/includes/admin/partials/import-edit.php"
fi

# Modify License file for FREE - create stub with basic methods
if [ -f "$FREE_BUILD_DIR/includes/class-wc-xml-csv-ai-import-license.php" ]; then
    # Replace License class with a FREE stub that returns 'free' tier
    cat > "$FREE_BUILD_DIR/includes/class-wc-xml-csv-ai-import-license.php" << 'EOFLIC'
<?php
/**
 * License stub for FREE version
 * Returns 'free' tier and upgrade URL, no API calls
 */
if (!defined('WPINC')) {
    die;
}

class WC_XML_CSV_AI_Import_License {
    public static function get_tier() {
        return 'free';
    }
    
    public static function is_pro() {
        return false;
    }
    
    public static function is_feature_available($feature) {
        $free_features = array('manual_import', 'xml_import', 'csv_import', 'field_mapping', 'variable_products', 'attributes', 'unlimited_products');
        return in_array($feature, $free_features);
    }
    
    public static function get_upgrade_url() {
        return 'https://bootflow.io/woocommerce-xml-csv-importer/';
    }
    
    public static function get_tier_name($tier = null) {
        return 'Free';
    }
    
    public static function activate_license($license_key) {
        return array('success' => false, 'message' => __('License activation is only available in the Pro version.', 'wc-xml-csv-import'));
    }
    
    public static function deactivate_license() {
        return array('success' => false, 'message' => __('License deactivation is only available in the Pro version.', 'wc-xml-csv-import'));
    }
}

function wc_xml_csv_ai_get_tier() {
    return 'free';
}
EOFLIC
fi

# Rename main plugin file to match slug
mv "$FREE_BUILD_DIR/woocommerce-xml-csv-importer.php" "$FREE_BUILD_DIR/$PLUGIN_SLUG.php"

# Create FREE ZIP
echo -e "${YELLOW}  Creating ZIP archive...${NC}"
cd "$BUILD_DIR"
zip -rq "$DIST_DIR/$PLUGIN_SLUG.zip" "$PLUGIN_SLUG"

echo -e "${GREEN}  ✓ FREE version created: $PLUGIN_SLUG.zip${NC}"

# ========================================
# BUILD PRO VERSION
# ========================================
echo ""
echo -e "${GREEN}[2/4] Building PRO version...${NC}"

PRO_BUILD_DIR="$BUILD_DIR/$PRO_SLUG"
mkdir -p "$PRO_BUILD_DIR"

# Copy all files except those in exclude list
rsync -av \
    --exclude-from="$SCRIPT_DIR/build-config/pro-exclude.txt" \
    --exclude="dist/" \
    --exclude="build-temp/" \
    "$SRC_DIR/" "$PRO_BUILD_DIR/"

# Modify main plugin file for PRO version
echo -e "${YELLOW}  Modifying plugin for PRO edition...${NC}"

# Ensure IS_PRO is true (should already be, but just in case)
sed -i "s/define('WC_XML_CSV_AI_IMPORT_IS_PRO', false);/define('WC_XML_CSV_AI_IMPORT_IS_PRO', true);/g" \
    "$PRO_BUILD_DIR/woocommerce-xml-csv-importer.php"

# Update plugin header for PRO
sed -i "s/Plugin Name: Bootflow – WooCommerce XML & CSV Importer/Plugin Name: Bootflow – WooCommerce XML \& CSV Importer (Pro)/g" \
    "$PRO_BUILD_DIR/woocommerce-xml-csv-importer.php"

# Update description for PRO
sed -i "s/Description: Import and update WooCommerce products from XML and CSV feeds with manual field mapping, product variations support, and a reliable import workflow./Description: Advanced automation, scheduled imports, selective updates, and AI-assisted workflows for WooCommerce XML and CSV product feeds./g" \
    "$PRO_BUILD_DIR/woocommerce-xml-csv-importer.php"

# Update version number in PRO
sed -i "s/Version: [0-9.]*$/Version: ${VERSION}/g" \
    "$PRO_BUILD_DIR/woocommerce-xml-csv-importer.php"

# Rename main plugin file to match slug
mv "$PRO_BUILD_DIR/woocommerce-xml-csv-importer.php" "$PRO_BUILD_DIR/$PRO_SLUG.php"

# Create PRO ZIP
echo -e "${YELLOW}  Creating ZIP archive...${NC}"
cd "$BUILD_DIR"
zip -rq "$DIST_DIR/$PRO_SLUG.zip" "$PRO_SLUG"

echo -e "${GREEN}  ✓ PRO version created: $PRO_SLUG.zip${NC}"

# ========================================
# VERIFICATION
# ========================================
echo ""
echo -e "${GREEN}[3/4] Verifying builds...${NC}"

# Check FREE build
FREE_SIZE=$(du -h "$DIST_DIR/$PLUGIN_SLUG.zip" | cut -f1)
echo -e "  FREE: $FREE_SIZE"

# Check PRO build  
PRO_SIZE=$(du -h "$DIST_DIR/$PRO_SLUG.zip" | cut -f1)
echo -e "  PRO:  $PRO_SIZE"

# Verify FREE doesn't have Pro files
echo -e "${YELLOW}  Checking FREE version doesn't contain Pro files...${NC}"
cd "$BUILD_DIR"
if unzip -l "$DIST_DIR/$PLUGIN_SLUG.zip" | grep -q "ai-providers"; then
    echo -e "${RED}  ✗ ERROR: FREE version contains ai-providers.php!${NC}"
    exit 1
else
    echo -e "${GREEN}  ✓ No AI providers in FREE version${NC}"
fi

if unzip -l "$DIST_DIR/$PLUGIN_SLUG.zip" | grep -q "scheduler"; then
    echo -e "${RED}  ✗ ERROR: FREE version contains scheduler.php!${NC}"
    exit 1
else
    echo -e "${GREEN}  ✓ No scheduler in FREE version${NC}"
fi

# ========================================
# CLEANUP
# ========================================
echo ""
echo -e "${GREEN}[4/4] Cleaning up...${NC}"
rm -rf "$BUILD_DIR"

# ========================================
# DONE
# ========================================
echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Build Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "Output files in ${YELLOW}dist/${NC}:"
echo -e "  📦 ${PLUGIN_SLUG}.zip (FREE - WordPress.org)"
echo -e "  📦 ${PRO_SLUG}.zip (PRO - bootflow.io)"
echo ""
echo -e "FREE version: ${GREEN}WordPress.org compliant${NC}"
echo -e "  - No AI processing"
echo -e "  - No scheduled imports"
echo -e "  - No eval() or external API calls"
echo ""
echo -e "PRO version: ${GREEN}Full features${NC}"
echo -e "  - AI auto-mapping"
echo -e "  - Scheduled imports"
echo -e "  - Variable products"
echo -e "  - Import filters"
echo -e "  - All processing modes"
echo ""

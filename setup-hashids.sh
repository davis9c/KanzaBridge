#!/bin/bash

##############################################################################
# Setup Script untuk Install Math Extensions untuk Hashids
# 
# Digunakan untuk menginstall bcmath atau gmp extension di PHP
# Kompatibel dengan: Linux/Ubuntu/Debian, WSL, dan Docker
#
# Usage: ./setup-hashids.sh [bcmath|gmp]
##############################################################################

set -e

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default to bcmath
EXTENSION=${1:-bcmath}

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Hashids Math Extension Setup${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${YELLOW}⚠ This script requires sudo privileges${NC}"
    echo -e "${YELLOW}Re-running with sudo...${NC}"
    exec sudo bash "$0" "$@"
fi

# Detect PHP version
PHP_VERSION=$(php -r "echo PHP_VERSION_ID / 10000;" 2>/dev/null || echo "8.3")
echo -e "${BLUE}Detected PHP Version: ${PHP_VERSION}${NC}"

# Detect OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    echo -e "${BLUE}Detected OS: ${OS}${NC}"
else
    echo -e "${RED}✗ Unable to detect OS${NC}"
    exit 1
fi

echo ""
echo -e "${BLUE}Installing ${EXTENSION} extension...${NC}"
echo ""

# Install based on OS
case "$OS" in
    ubuntu|debian|raspbian)
        echo -e "${BLUE}→ Updating package list...${NC}"
        apt-get update -qq
        
        echo -e "${BLUE}→ Installing php-${EXTENSION}...${NC}"
        apt-get install -y php-${EXTENSION}
        
        echo -e "${GREEN}✓ php-${EXTENSION} installed${NC}"
        ;;
    
    alpine)
        echo -e "${BLUE}→ Installing php-${EXTENSION}...${NC}"
        apk add --no-cache php-${EXTENSION}
        
        echo -e "${GREEN}✓ php-${EXTENSION} installed${NC}"
        ;;
    
    centos|rhel|fedora)
        echo -e "${BLUE}→ Installing php-${EXTENSION}...${NC}"
        yum install -y php-${EXTENSION}
        
        echo -e "${GREEN}✓ php-${EXTENSION} installed${NC}"
        ;;
    
    *)
        echo -e "${RED}✗ Unsupported OS: ${OS}${NC}"
        echo -e "${YELLOW}Please install php-${EXTENSION} manually${NC}"
        exit 1
        ;;
esac

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Enabling ${EXTENSION} for all SAPIs${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Enable for CLI
if command -v phpenmod &> /dev/null; then
    echo -e "${BLUE}→ Enabling for CLI...${NC}"
    phpenmod -s cli ${EXTENSION} 2>/dev/null || true
    echo -e "${GREEN}✓ Enabled for CLI${NC}"
fi

# Enable for Apache2
if command -v a2enmod &> /dev/null; then
    echo -e "${BLUE}→ Enabling for Apache2...${NC}"
    phpenmod -s apache2 ${EXTENSION} 2>/dev/null || true
    echo -e "${GREEN}✓ Enabled for Apache2${NC}"
fi

# Enable for PHP-FPM
if command -v systemctl &> /dev/null; then
    if systemctl list-unit-files | grep -q "php.*-fpm"; then
        echo -e "${BLUE}→ Enabling for PHP-FPM...${NC}"
        phpenmod -s fpm ${EXTENSION} 2>/dev/null || true
        echo -e "${GREEN}✓ Enabled for PHP-FPM${NC}"
    fi
fi

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Restarting Services${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Restart Apache
if command -v systemctl &> /dev/null && systemctl list-unit-files | grep -q "apache2"; then
    echo -e "${BLUE}→ Restarting Apache2...${NC}"
    systemctl restart apache2
    echo -e "${GREEN}✓ Apache2 restarted${NC}"
fi

# Restart PHP-FPM
if command -v systemctl &> /dev/null && systemctl list-unit-files | grep -q "php.*-fpm"; then
    echo -e "${BLUE}→ Restarting PHP-FPM...${NC}"
    systemctl restart php${PHP_VERSION}-fpm
    echo -e "${GREEN}✓ PHP-FPM restarted${NC}"
fi

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Verifying Installation${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Verify installation
if php -m | grep -qi ${EXTENSION}; then
    echo -e "${GREEN}✓ ${EXTENSION} successfully installed and loaded!${NC}"
    php -i | grep ${EXTENSION} | head -1
else
    echo -e "${RED}✗ Failed to verify ${EXTENSION} installation${NC}"
    exit 1
fi

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${GREEN}✓ Setup completed successfully!${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "1. Test Hashids feature in your application"
echo "2. Run: ${BLUE}cd /path/to/project && php spark test${NC}"
echo "3. Or access diagnose page: ${BLUE}/diagnose/hashid${NC}"
echo ""

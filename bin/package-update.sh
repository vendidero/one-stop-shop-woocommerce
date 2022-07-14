#!/bin/sh

# Output colorized strings
#
# Color codes:
# 0 - black
# 1 - red
# 2 - green
# 3 - yellow
# 4 - blue
# 5 - magenta
# 6 - cian
# 7 - white
output() {
	echo "$(tput setaf "$1")$2$(tput sgr0)"
}

if [ ! -d "libs/" ]; then
	output 1 "./libs doesn't exist!"
	output 1 "run \"composer install\" before proceed."
fi

# Autoloader
output 3 "Updating autoloader classmaps..."
composer dump-autoload
output 2 "Done"

# Convert textdomains
output 3 "Updating package textdomains..."

# Replace text domains within packages with woocommerce
find ./libs/woocommerce-eu-tax-helper -iname '*.php' -exec sed -i.bak -e "s/, 'woocommerce-eu-tax-helper'/, 'oss-woocommerce'/g" {} \;

rm -rf ./libs/woocommerce-eu-tax-helper/vendor

output 2 "Done!"

# Cleanup backup files
find ./libs -name "*.bak" -type f -delete
output 2 "Done!"
#!/bin/bash

# Clear the Netshot cache and run the test script
# This script is a shortcut for running test_netshot_api.sh with the --clear-cache option

# Define base directory
BASE_DIR=$(dirname "$0")

# Clear cache directory
CACHE_DIR="${BASE_DIR}/cache/netshot"
if [ -d "$CACHE_DIR" ]; then
    echo "Clearing Netshot API cache..."
    rm -rf "$CACHE_DIR"/*
    echo "Cache cleared!"
else
    echo "Creating Netshot cache directory..."
    mkdir -p "$CACHE_DIR"
    echo "Cache directory created!"
fi

# Run the test script with all arguments plus clear-cache option
"${BASE_DIR}/test_netshot_api.sh" --clear-cache "$@"

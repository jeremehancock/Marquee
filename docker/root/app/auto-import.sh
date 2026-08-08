#!/bin/bash
# Runs one scheduled auto-import. Invoked by cron as the "abc" user.
#
# No environment sourcing: the cron service starts under with-contenv, so crond
# inherits the container environment and hands it to this script.

php /app/www/bin/auto-import.php >> /config/data/auto-import.log 2>&1

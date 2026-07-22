#!/bin/bash
# Runs one scheduled auto-import. Invoked by cron as the "abc" user.

if [ -f /app/docker-env.sh ]; then
    # shellcheck disable=SC1091
    source /app/docker-env.sh
fi

php /app/www/bin/auto-import.php >> /config/data/auto-import.log 2>&1

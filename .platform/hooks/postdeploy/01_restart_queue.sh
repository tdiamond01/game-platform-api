#!/bin/bash

# Restart queue workers after deployment
cd /var/app/current

# Clear any cached jobs and restart workers
php artisan queue:restart

echo "Queue workers restarted successfully"

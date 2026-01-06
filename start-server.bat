@echo off
echo Starting PHP Server...
start "" "http://localhost:8000"
php -S localhost:8000 -t www

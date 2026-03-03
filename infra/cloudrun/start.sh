#!/bin/sh
set -e

: "${PORT:=8080}"

envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/http.d/default.conf

php-fpm -D
exec nginx -g 'daemon off;'

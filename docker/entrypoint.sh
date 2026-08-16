#!/usr/bin/env sh
set -eu

# Railway dapat mengaktifkan MPM tambahan saat container dimulai. Pastikan hanya
# prefork yang aktif karena mod_php tidak boleh berjalan bersama event/worker.
rm -f \
    /etc/apache2/mods-enabled/mpm_event.load \
    /etc/apache2/mods-enabled/mpm_event.conf \
    /etc/apache2/mods-enabled/mpm_worker.load \
    /etc/apache2/mods-enabled/mpm_worker.conf
a2enmod -f mpm_prefork >/dev/null

mpm_count="$(find /etc/apache2/mods-enabled -maxdepth 1 -name 'mpm_*.load' | wc -l | tr -d ' ')"
if [ "$mpm_count" != "1" ]; then
    echo "Apache harus memiliki tepat satu MPM aktif; ditemukan: $mpm_count" >&2
    find /etc/apache2/mods-enabled -maxdepth 1 -name 'mpm_*.load' -print >&2
    exit 1
fi

apache2ctl -t

if [ "${SKIP_APP_BOOTSTRAP:-false}" != "true" ]; then
    php artisan migrate --force
    php artisan db:seed --force
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"

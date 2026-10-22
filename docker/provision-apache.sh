#!/usr/bin/env bash
#
# Makes sure this stack has an Apache vhost pointing at it.
#
# TLS is deliberately NOT handled here — issue the certificate yourself, once:
#
#   sudo certbot --apache -d pos-api.hayzeeonline.com
#
# Usage:
#
#   sudo ./docker/provision-apache.sh <domain> <app-port>
#   sudo ./docker/provision-apache.sh pos-api.hayzeeonline.com 8092
#
# Runs on every deploy, so it is careful about two things:
#
#   1. It NEVER rewrites a vhost that already exists. `certbot --apache` edits
#      that file in place — adding the redirect to :443 — so regenerating it
#      from the template on a later deploy would quietly undo your TLS setup.
#      Once the file exists it belongs to you.
#   2. It verifies Apache actually serves the name afterwards, rather than
#      trusting that a2ensite and a reload did what they were asked.

set -Eeuo pipefail

DOMAIN="${1:?usage: provision-apache.sh <domain> <app-port>}"
PORT="${2:?usage: provision-apache.sh <domain> <app-port>}"

TEMPLATE="$(dirname "$0")/apache-vhost.conf.example"
SITE="/etc/apache2/sites-available/${DOMAIN}.conf"
LINK="/etc/apache2/sites-enabled/${DOMAIN}.conf"

if [[ $EUID -ne 0 ]]; then
    echo "provision: needs root." >&2
    echo "           The deploy runs this as: sudo -S provision-apache.sh ..." >&2
    echo "           so '${SUDO_USER:-$USER}' must be able to sudo on this box." >&2
    exit 1
fi

command -v apache2ctl >/dev/null || { echo "provision: apache2 not installed" >&2; exit 1; }
[[ -f $TEMPLATE ]] || { echo "provision: $TEMPLATE not found" >&2; exit 1; }

# Deliberately not `apache2ctl -S | grep -q`. Under `set -o pipefail` that is
# a trap: grep -q exits the moment it matches, apache2ctl is still writing, it
# takes SIGPIPE, and pipefail reports the whole pipeline as failed — so the
# check fails *because* the domain was found. It only bites once the vhost map
# is long enough for the match to land early.
serves_domain() {
    local map
    map="$(apache2ctl -S 2>&1)" || return 1
    case "$map" in
        *"${DOMAIN}"*) return 0 ;;
        *)             return 1 ;;
    esac
}

# ---- fast path --------------------------------------------------------------
# Asking Apache whether it serves the name matters as much as the file
# existing: the site can be disabled, or another vhost can shadow it, and
# every request then falls through to the default page.
if [[ -f $SITE ]] && [[ -L $LINK ]] && serves_domain; then
    echo "provision: ${DOMAIN} already served — nothing to do"
    exit 0
fi

# ---- modules (a2enmod is idempotent) ---------------------------------------
a2enmod proxy proxy_http headers rewrite ssl >/dev/null

# ---- vhost ------------------------------------------------------------------
if [[ -f $SITE ]]; then
    echo "provision: ${SITE} exists — leaving its contents alone"
    echo "provision: (delete it and redeploy if you want it regenerated)"
else
    echo "provision: writing ${SITE} -> 127.0.0.1:${PORT}"
    sed -e "s|__DOMAIN__|${DOMAIN}|g" -e "s|__PORT__|${PORT}|g" "$TEMPLATE" > "$SITE"
fi

a2ensite "${DOMAIN}" >/dev/null

if [[ ! -L $LINK ]]; then
    echo "provision: a2ensite did not create ${LINK}" >&2
    exit 1
fi

if ! apache2ctl configtest; then
    echo "provision: config test failed — disabling ${DOMAIN} again" >&2
    a2dissite "${DOMAIN}" >/dev/null || true
    systemctl reload apache2 || true
    exit 1
fi

# Reload, not restart: connections to the other sites on this box are not
# dropped.
systemctl reload apache2

if ! serves_domain; then
    echo "provision: Apache does not list ${DOMAIN} as a served name." >&2
    echo "provision: vhost map follows —" >&2
    apache2ctl -S 2>&1 | sed 's/^/    /' >&2
    exit 1
fi

echo "provision: ${DOMAIN} -> 127.0.0.1:${PORT} on :80"
echo "provision: TLS not managed here — sudo certbot --apache -d ${DOMAIN}"

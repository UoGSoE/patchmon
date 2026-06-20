#!/usr/bin/env bash
#
# record_patched.sh — tell Patchmon this server has been patched.
#
# First run (no token yet): provisions a per-machine token from Patchmon by the
# server's FQDN, saves it to the local config file, then records the patch.
# Every run after that just records the patch with the saved token.
#
# Download a copy (with this install's URL pre-filled) from Patchmon:
#   Settings → API examples → Download record_patched.sh
#
# Manual test checklist (the bash itself has no automated tests by design;
# Patchmon's endpoints are covered by the PHP test suite):
#   1. Fresh box, no /etc/patchmon.env  → provisions, writes config 0600, records.
#   2. Second run                       → uses the saved token, records, no re-provision.
#   3. Provision an already-provisioned FQDN without resetting in the UI
#                                       → 409 message, exits non-zero.
#   4. Run as a non-root user           → prints the token instead of writing the
#                                         file, still records the patch.
#   5. Wrong/rotated token (HTTP 404)   → see the note in record_patch() below.
#
set -euo pipefail

CONFIG_FILE="/etc/patchmon.env"

# Patchmon URL. Pre-filled for this install when downloaded from the app; an
# existing environment value, Puppet, or ${CONFIG_FILE} can still override it.
PATCHMON_URL="${PATCHMON_URL:-__PATCHMON_URL__}"

log() { printf 'Patchmon: %s\n' "$1"; }
die() { printf 'Patchmon: %s\n' "$1" >&2; exit 1; }

# Load local config if present — it may set PATCHMON_URL and PATCHMON_TOKEN.
if [ -f "${CONFIG_FILE}" ]; then
    # shellcheck disable=SC1090,SC1091
    . "${CONFIG_FILE}"
fi

FQDN="$(hostname -f 2>/dev/null || hostname)"
[ -n "${FQDN}" ] || die "could not determine this server's hostname"

# POST and echo the HTTP status code. Body (if any) is written to the path in $1.
post_status() {
    local body_file="$1"
    shift
    curl -sS -o "${body_file}" -w '%{http_code}' -X POST "$@"
}

record_patch() {
    local code
    code="$(post_status /dev/null "${PATCHMON_URL}/record-patch/${PATCHMON_TOKEN}")" \
        || die "could not reach Patchmon at ${PATCHMON_URL}"

    case "${code}" in
        200 | 204)
            log "recorded patch for ${FQDN}"
            ;;
        404)
            # The saved token was not recognised — most likely it was regenerated
            # in Patchmon's web UI while this box kept the old one.
            #
            # NOTE (parked decision): we deliberately do NOT auto-re-provision here.
            # To re-enrol, clear PATCHMON_TOKEN from ${CONFIG_FILE} and run again.
            die "this server's token was not recognised. It may have been regenerated in Patchmon's web UI — clear PATCHMON_TOKEN from ${CONFIG_FILE} and re-run to re-enrol."
            ;;
        *)
            die "unexpected response (${code}) recording patch for ${FQDN}"
            ;;
    esac
}

provision() {
    log "no PATCHMON_TOKEN found in ${CONFIG_FILE}"
    log "requesting first-run token for ${FQDN}"

    local body_file code body token
    body_file="$(mktemp)"
    code="$(post_status "${body_file}" \
        -H 'Content-Type: application/json' \
        -d "{\"fqdn\":\"${FQDN}\"}" \
        "${PATCHMON_URL}/record-patch/provision")" \
        || { rm -f "${body_file}"; die "could not reach Patchmon at ${PATCHMON_URL}"; }
    body="$(cat "${body_file}")"
    rm -f "${body_file}"

    case "${code}" in
        200)
            if command -v jq >/dev/null 2>&1; then
                token="$(printf '%s' "${body}" | jq -r '.patch_token')"
            else
                token="$(printf '%s' "${body}" | sed -n 's/.*"patch_token":"\([^"]*\)".*/\1/p')"
            fi
            if [ -z "${token}" ] || [ "${token}" = "null" ]; then
                die "provision response contained no token"
            fi
            PATCHMON_TOKEN="${token}"
            save_config
            ;;
        409)
            die "a token has already been provisioned for ${FQDN}. Reset it in Patchmon's web UI, then run again."
            ;;
        429)
            die "Patchmon is rate-limiting provision requests — wait a moment and try again."
            ;;
        422)
            die "Patchmon rejected the hostname '${FQDN}' — it must be a fully-qualified domain name."
            ;;
        *)
            die "unexpected response (${code}) requesting a token for ${FQDN}"
            ;;
    esac
}

save_config() {
    if [ "$(id -u)" -ne 0 ]; then
        log "not running as root — could not write ${CONFIG_FILE}"
        log "save this line yourself: PATCHMON_TOKEN=\"${PATCHMON_TOKEN}\""
        return 0
    fi

    ( umask 077; printf 'PATCHMON_URL="%s"\nPATCHMON_TOKEN="%s"\n' \
        "${PATCHMON_URL}" "${PATCHMON_TOKEN}" > "${CONFIG_FILE}" )
    chmod 0600 "${CONFIG_FILE}"
    log "token saved to ${CONFIG_FILE}"
}

# A rendered download fills PATCHMON_URL in above; the raw script (or a missing
# override) leaves it as the unfilled placeholder, which won't look like a URL.
case "${PATCHMON_URL}" in
    http://* | https://*) ;;
    *) die "PATCHMON_URL is not set — edit this script or ${CONFIG_FILE}" ;;
esac

if [ -n "${PATCHMON_TOKEN:-}" ]; then
    record_patch
else
    provision
    record_patch
fi

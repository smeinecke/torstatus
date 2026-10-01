#!/bin/bash
# Generate a hashed control password from the environment when provided.
# The static HashedControlPassword in torrc remains the fallback for local dev.
set -e

TORRC=/etc/tor/torrc
if [ -n "${TOR_CONTROL_PASSWORD:-}" ]; then
    HASH=$(/usr/bin/tor --hash-password "$TOR_CONTROL_PASSWORD" --quiet)
    # Strip any HashedControlPassword from the base torrc — Tor accepts auth
    # against every configured hash, so the static dev password must not
    # remain valid when a custom one is provided.
    grep -v '^[[:space:]]*HashedControlPassword' /etc/tor/torrc > /run/tor/torrc
    printf 'HashedControlPassword %s\n' "$HASH" >> /run/tor/torrc
    chown debian-tor:debian-tor /run/tor/torrc
    TORRC=/run/tor/torrc
fi

exec /usr/bin/tor --defaults-torrc /usr/share/tor/tor-service-defaults-torrc -f "$TORRC" --RunAsDaemon 0

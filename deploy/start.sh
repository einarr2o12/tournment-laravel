#!/usr/bin/env sh
# =============================================================================
# Entrypoint dispatcher (Railway).
#
# One Docker image runs as TWO services (web + reverb). Each service sets
# SERVICE_TYPE; this script execs the matching start script. The web service
# leaves SERVICE_TYPE unset (or "web"); the reverb service sets it to "reverb".
# =============================================================================
if [ "$SERVICE_TYPE" = "reverb" ]; then
  exec /usr/local/bin/start-reverb.sh
else
  exec /usr/local/bin/start-web.sh
fi

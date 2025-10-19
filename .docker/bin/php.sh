#!/usr/bin/env bash
set -euo pipefail

# Handle non-interactive executions (e.g. CI).
if [ -t 0 ] && [ -t 1 ]; then
  TTY_FLAG=""
else
  TTY_FLAG="-T"
fi

exec docker compose exec ${TTY_FLAG} octane php "$@"

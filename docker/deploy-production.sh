#!/usr/bin/env bash

set -Eeuo pipefail

readonly application_root="/opt/fleet-monitoring"
readonly branch="main"
readonly revision_file="${application_root}/.deployed_revision"

exec 9>"/var/lock/fleet-monitoring-deploy.lock"
flock -n 9 || exit 0

cd "${application_root}"

git fetch origin "${branch}"

target_revision="$(git rev-parse "origin/${branch}")"
deployed_revision="$(cat "${revision_file}" 2>/dev/null || true)"

if [[ "${target_revision}" == "${deployed_revision}" ]]; then
    exit 0
fi

git reset --hard "${target_revision}"

docker run --rm \
    --volume "${application_root}/car-maintenance:/app" \
    --workdir /app \
    node:22-alpine \
    sh -c 'npm ci && npm run build'

docker compose -f docker-compose.prod.yml build app queue scheduler
docker compose -f docker-compose.prod.yml up -d app queue scheduler
docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec -T app php artisan optimize
docker compose -f docker-compose.prod.yml restart web

for attempt in {1..30}; do
    if curl --fail --silent --show-error --max-time 5 https://motologic.tech/login >/dev/null; then
        printf '%s\n' "${target_revision}" > "${revision_file}"
        exit 0
    fi

    sleep 2
done

echo 'Production health check failed after deployment.' >&2
exit 1

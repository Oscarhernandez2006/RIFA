#!/bin/bash
set -euo pipefail

SERVICE_NAME="${1:-rifa-apprifa-5hzqgt}"

docker service update \
  --label-add 'traefik.enable=true' \
  --label-add 'traefik.docker.network=dokploy-network' \
  --label-add 'traefik.http.routers.premios.rule=Host(`premios.grupo-santacruz.com`)' \
  --label-add 'traefik.http.routers.premios.entrypoints=websecure' \
  --label-add 'traefik.http.routers.premios.tls=true' \
  --label-add 'traefik.http.routers.premios.tls.certresolver=letsencrypt' \
  --label-add 'traefik.http.routers.premios.service=premios' \
  --label-add 'traefik.http.services.premios.loadbalancer.server.port=80' \
  --label-add 'traefik.http.routers.premios-web.rule=Host(`premios.grupo-santacruz.com`)' \
  --label-add 'traefik.http.routers.premios-web.entrypoints=web' \
  --label-add 'traefik.http.routers.premios-web.service=premios' \
  --label-add 'traefik.http.routers.premios-web.middlewares=premios-redirect-https' \
  --label-add 'traefik.http.middlewares.premios-redirect-https.redirectscheme.scheme=https' \
  --label-add 'traefik.http.middlewares.premios-redirect-https.redirectscheme.permanent=true' \
  "$SERVICE_NAME"

echo "Labels aplicados correctamente a $SERVICE_NAME"
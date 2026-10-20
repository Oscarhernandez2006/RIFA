SERVICE=rifa-apprifa-5hzqgt

sudo docker service update \
  --label-add 'traefik.enable=true' \
  --label-add 'traefik.http.routers.premios-web.rule=Host(`premios.grupo-santacruz.com`)' \
  --label-add 'traefik.http.routers.premios-web.entrypoints=web' \
  --label-add 'traefik.http.routers.premios-web.middlewares=redirect-to-https@file' \
  --label-add 'traefik.http.routers.premios-web.service=premios' \
  --label-add 'traefik.http.routers.premios-websecure.rule=Host(`premios.grupo-santacruz.com`)' \
  --label-add 'traefik.http.routers.premios-websecure.entrypoints=websecure' \
  --label-add 'traefik.http.routers.premios-websecure.tls.certresolver=letsencrypt' \
  --label-add 'traefik.http.routers.premios-websecure.service=premios' \
  --label-add 'traefik.http.services.premios.loadbalancer.server.port=80' \
  "$SERVICE"
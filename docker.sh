#!/bin/bash

docker stop ha-connectlife-addon
docker rm ha-connectlife-addon

docker build . --build-arg='BUILD_FROM=alpine:3.19' -t ha-connectlife-addon

docker run --restart=always --name ha-connectlife-addon -d \
-p 8000:8000 \
-e CONNECTLIFE_LOGIN=$EMAIL \
-e CONNECTLIFE_PASSWORD=$PASSWORD \
-e LOG_LEVEL=info \
-e MQTT_HOST=$HOST \
-e MQTT_USER=$USER  \
-e MQTT_PASSWORD=$PASSWORD  \
-e MQTT_PORT=1883 \
-e MQTT_SSL=false \
ha-connectlife-addon /bin/ash -c '/usr/bin/supervisord -c /home/app/docker-files/supervisord.conf'

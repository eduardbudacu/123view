#!/bin/bash

# Check if Docker daemon is available
if docker info > /dev/null 2>&1; then
  CONTAINERS=$(docker ps -a --filter "label=com.docker.compose.project=123view" -q)
  if [ -n "$CONTAINERS" ]; then
    docker stop $CONTAINERS
    docker rm $CONTAINERS
  fi
else
  echo "Docker daemon is not available. Skipping Docker cleanup."
fi
rm -rf "$(dirname "$0")/../vendor/"
rm -rf "$(dirname "$0")/../var"

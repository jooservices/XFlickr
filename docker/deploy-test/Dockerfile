# Ubuntu 22.04 harness for deploy script unit tests (not macOS host).
FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends \
    bash \
    ca-certificates \
    curl \
    git \
    shellcheck \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

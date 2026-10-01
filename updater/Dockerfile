FROM python:3.13-slim

RUN apt-get update && \
    apt-get -y dist-upgrade && \
    apt-get -y install monitoring-plugins-basic psmisc && \
    apt-get -y autoremove && \
    apt-get -y purge $(dpkg -l | grep '^rc' | awk '{print $2}') && \
    apt-get clean

# Install uv from the pinned official image (no curl|sh)
COPY --from=ghcr.io/astral-sh/uv:0.9.2 /uv /usr/local/bin/uv

RUN mkdir -p /opt/torstatus
WORKDIR /opt/torstatus

# Copy dependency files first for layer caching
COPY pyproject.toml uv.lock README.md ./
COPY src ./src

# Install dependencies and package
RUN uv sync --locked --no-dev

RUN addgroup --gid 1026 updater && \
    adduser --disabled-password --ingroup updater --system --home /opt/torstatus updater && \
    chown -R updater:updater /opt/torstatus

ENV UV_CACHE_DIR=/opt/torstatus/.uv-cache
USER updater

# Run updater in a loop: 15 min on success, 15 sec on failure
CMD ["/bin/sh", "-c", "while true; do uv run --no-sync python -m torstatus_updater && sleep 900 || sleep 15; done"]

FROM debian:bookworm-slim

ARG PHP_VERSION=5.5.38
ARG PHP_SHA256=4f458c9b504269615715a62f182b7c2f89bb8284f484befc221b56a1571b506e

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        gcc \
        make \
        libc6-dev \
        libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL \
      "https://museum.php.net/php5/php-${PHP_VERSION}.tar.gz" \
      -o /tmp/php.tar.gz \
    && echo "${PHP_SHA256}  /tmp/php.tar.gz" | sha256sum -c - \
    && mkdir /tmp/php-src \
    && tar -xzf /tmp/php.tar.gz \
        -C /tmp/php-src \
        --strip-components=1 \
    && cd /tmp/php-src \
    && ./configure \
        --disable-all \
        --enable-cli \
        --enable-json \
    && make -j"$(nproc)" \
    && make install \
    && rm -rf /tmp/php-src /tmp/php.tar.gz

WORKDIR /app

CMD ["php", "-v"]
